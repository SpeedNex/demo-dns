// Package validation provides profile/owner/subscription checks for DoH
// and UDP/TCP resolution paths (UI.md #40).
package validation

import (
	"errors"
	"strings"
)

// Status represents the lifecycle state of a profile.
type Status string

const (
	StatusActive    Status = "active"
	StatusInactive  Status = "inactive"
	StatusSuspended Status = "suspended"
)

// ErrProfileNotFound is returned when a profile UID does not exist.
var ErrProfileNotFound = errors.New("profile not found")

// ErrProfileNotOwned is returned when a profile belongs to another user.
var ErrProfileNotOwned = errors.New("profile not owned by user")

// ErrProfileInactive is returned when a profile is disabled or suspended.
var ErrProfileInactive = errors.New("profile inactive")

// ErrSubscriptionInactive is returned when the user's subscription is
// not active.
var ErrSubscriptionInactive = errors.New("subscription inactive")

// ProfileLookup is the minimal contract the validator needs.  The
// control plane or local active.json adapter implements this; tests
// can supply a stub.
type ProfileLookup interface {
	GetProfile(profileID string) (Profile, error)
	GetActiveSubscription(userID string) (Subscription, error)
}

// Profile is the minimal profile record.
type Profile struct {
	ID     string
	UserID string
	Status Status
}

// Subscription is the minimal subscription record.
type Subscription struct {
	UserID string
	Status Status
}

// Validator checks profile ownership and subscription status.
type Validator struct {
	lookup ProfileLookup
}

// New creates a new Validator.
func New(lookup ProfileLookup) *Validator {
	return &Validator{lookup: lookup}
}

// Validate checks the four required invariants from UI.md #40:
//  1. profile exists
//  2. profile is active
//  3. user owns the profile
//  4. user has an active subscription
func (v *Validator) Validate(profileID, userID string) error {
	profileID = strings.TrimSpace(profileID)
	userID = strings.TrimSpace(userID)
	if profileID == "" {
		return ErrProfileNotFound
	}

	profile, err := v.lookup.GetProfile(profileID)
	if err != nil {
		return ErrProfileNotFound
	}
	if profile.UserID != userID {
		return ErrProfileNotOwned
	}
	if profile.Status != StatusActive {
		return ErrProfileInactive
	}

	if userID != "" {
		sub, err := v.lookup.GetActiveSubscription(userID)
		if err == nil && sub.Status != StatusActive {
			return ErrSubscriptionInactive
		}
	}
	return nil
}
