# syntax=docker/dockerfile:1.7

# ---------- Builder ----------
# 与 go.mod 中 "go 1.24" 对齐；不要追到 1.25，避免 CI 触发 toolchain 下载。
FROM golang:1.24-bookworm AS builder

ARG TARGETOS=linux
ARG TARGETARCH=amd64
ARG SERVICE=dns-resolver

WORKDIR /src

# Cache module downloads
COPY go.mod go.sum ./
RUN --mount=type=cache,target=/go/pkg/mod \
    go mod download

COPY . .

# Build the requested service as a static, stripped binary.
# CGO_ENABLED=0 keeps the image small and lets us run on scratch.
RUN --mount=type=cache,target=/go/pkg/mod \
    --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 GOOS=${TARGETOS} GOARCH=${TARGETARCH} \
    go build -trimpath -ldflags="-s -w" \
        -o /out/${SERVICE} ./cmd/${SERVICE}

# ---------- Runtime ----------
FROM gcr.io/distroless/static-debian12:nonroot AS runtime

ARG SERVICE=dns-resolver
ENV SERVICE=${SERVICE}

COPY --from=builder /out/${SERVICE} /usr/local/bin/${SERVICE}

USER nonroot:nonroot

EXPOSE 53 853 8443 15354

# Distroless has no shell, so the healthcheck is a tiny self-contained Go
# binary. For now we use the standard liveness pattern of exec'ing the
# binary with --help; docker-compose will replace this with a tcp probe.
ENTRYPOINT ["/usr/local/bin/dns-resolver"]
