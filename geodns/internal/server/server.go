package server

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net/http"
	"sync"
	"time"

	"ocer-dns/geodns/internal/config"
	"ocer-dns/geodns/internal/healthview"
	"ocer-dns/geodns/internal/node"
	"ocer-dns/geodns/internal/router"
)

type Server struct {
	cfg       *config.Config
	router    *router.Router
	client    healthview.Client
	heartbeat *node.HeartbeatClient
	startTime time.Time

	mu   sync.RWMutex
	view healthview.View
}

func New(cfg *config.Config) *Server {
	r := router.New()

	timeout := cfg.RequestTimeout()
	hbSecret := cfg.NodeHMACSecret()
	hb := (*node.HeartbeatClient)(nil)
	if cfg.NodeToken() != "" && hbSecret != "" && cfg.NodeAPIEndpoint() != "" {
		hb = node.NewHeartbeatClient(cfg.NodeAPIEndpoint(), cfg.NodeToken(), hbSecret, timeout)
	} else {
		log.Printf("geodns: heartbeat disabled (token/secret/endpoint not all set)")
	}

	return &Server{
		cfg:       cfg,
		router:    r,
		client:    healthview.Client{
			BaseURL:    cfg.Server.ConsoleHealthURL,
			Token:      cfg.HealthViewToken(),
			HTTPClient: &http.Client{Timeout: timeout},
		},
		heartbeat: hb,
		startTime: time.Now(),
	}
}

func (s *Server) Run(ctx context.Context) error {
	if err := s.refreshOnce(ctx); err != nil {
		log.Printf("geodns: initial health view fetch failed: %v (will retry on schedule)", err)
	}

	refreshTicker := time.NewTicker(s.cfg.RefreshDuration())
	defer refreshTicker.Stop()

	heartbeatTicker := (*time.Ticker)(nil)
	if s.heartbeat != nil {
		// 心跳间隔 = 刷新间隔（保持节奏一致，最长 30s）
		hbInterval := s.cfg.RefreshDuration()
		if hbInterval > 30*time.Second {
			hbInterval = 30 * time.Second
		}
		heartbeatTicker = time.NewTicker(hbInterval)
		defer heartbeatTicker.Stop()
		// 立即发一次
		s.heartbeat.ReportWithStart(s.startTime, "geodns-local", 0, nil)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/health", s.handleHealth)
	mux.HandleFunc("/health-view", s.handleHealthView)
	mux.HandleFunc("/pick", s.handlePick)

	httpServer := &http.Server{
		Addr:              s.cfg.Server.ListenAddr,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
	}

	serverErr := make(chan error, 1)
	go func() {
		log.Printf("geodns: listening on %s (refresh=%s, source=%s)",
			s.cfg.Server.ListenAddr, s.cfg.RefreshDuration(), s.cfg.Server.ConsoleHealthURL)
		if err := httpServer.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			serverErr <- err
			return
		}
		serverErr <- nil
	}()

	for {
		select {
		case <-ctx.Done():
			shutdownCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
			defer cancel()
			if err := httpServer.Shutdown(shutdownCtx); err != nil {
				return fmt.Errorf("geodns: shutdown: %w", err)
			}
			return ctx.Err()
		case err := <-serverErr:
			if err != nil {
				return err
			}
			return nil
		case <-refreshTicker.C:
			if err := s.refreshOnce(ctx); err != nil {
				log.Printf("geodns: health view refresh failed: %v", err)
			}
		case <-tickerChan(heartbeatTicker):
			if s.heartbeat != nil {
				s.heartbeat.ReportWithStart(s.startTime, "geodns-local", 0, nil)
			}
		}
	}
}

func tickerChan(t *time.Ticker) <-chan time.Time {
	if t == nil {
		return nil
	}
	return t.C
}

func (s *Server) refreshOnce(ctx context.Context) error {
	if s.cfg.Server.ConsoleHealthURL == "" {
		return errors.New("console_health_url is empty")
	}

	fetchCtx, cancel := context.WithTimeout(ctx, s.cfg.RequestTimeout())
	defer cancel()

	view, err := s.client.Fetch(fetchCtx)
	if err != nil {
		return err
	}

	s.mu.Lock()
	s.view = view
	s.mu.Unlock()

	log.Printf("geodns: refreshed health view: %d node(s), generated_at=%s, ttl=%ds",
		len(view.Nodes), view.GeneratedAt.Format(time.RFC3339), view.TTLSeconds)
	return nil
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.Header().Set("Allow", "GET")
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(`{"status":"ok","service":"geodns"}`))
}

func (s *Server) handleHealthView(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.Header().Set("Allow", "GET")
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	s.mu.RLock()
	view := s.view
	s.mu.RUnlock()

	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Cache-Control", fmt.Sprintf("public, max-age=%d", s.boundedTTL(view.TTLSeconds)))
	if err := json.NewEncoder(w).Encode(map[string]any{"data": view}); err != nil {
		log.Printf("geodns: failed to encode health view: %v", err)
	}
}

func (s *Server) handlePick(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.Header().Set("Allow", "GET")
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	region := r.URL.Query().Get("region")
	if region == "" {
		http.Error(w, "missing region query parameter", http.StatusBadRequest)
		return
	}

	s.mu.RLock()
	view := s.view
	s.mu.RUnlock()

	pick := s.router.Pick(region, view.Nodes)
	w.Header().Set("Content-Type", "application/json")
	if pick == nil {
		w.WriteHeader(http.StatusServiceUnavailable)
		_, _ = w.Write([]byte(`{"data":null,"reason":"no_eligible_node"}`))
		return
	}
	_ = json.NewEncoder(w).Encode(map[string]any{"data": pick})
}

func (s *Server) boundedTTL(ttl int) int {
	if ttl <= 0 {
		return 0
	}
	if ttl > 60 {
		return 60
	}
	return ttl
}
