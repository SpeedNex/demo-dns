CREATE TABLE IF NOT EXISTS users (
    id uuid PRIMARY KEY,
    name varchar(100) NOT NULL,
    email varchar(255) NOT NULL,
    email_verified_at timestamptz NULL,
    password_hash varchar(255) NOT NULL,
    role varchar(30) NOT NULL DEFAULT 'member',
    status varchar(30) NOT NULL DEFAULT 'active',
    timezone varchar(64) NOT NULL DEFAULT 'UTC',
    locale varchar(20) NOT NULL DEFAULT 'en',
    current_plan_id uuid NULL,
    last_login_at timestamptz NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    deleted_at timestamptz NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_users_email ON users (lower(email));

CREATE TABLE IF NOT EXISTS dns_personal_access_tokens (
    id bigserial PRIMARY KEY,
    tokenable_type varchar(255) NOT NULL,
    tokenable_id varchar(36) NOT NULL,
    name varchar(255) NOT NULL,
    token varchar(64) NOT NULL,
    abilities text NULL,
    last_used_at timestamptz NULL,
    expires_at timestamptz NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_dns_personal_access_tokens_token
    ON dns_personal_access_tokens (token);

CREATE TABLE IF NOT EXISTS profiles (
    id uuid PRIMARY KEY,
    user_id uuid NOT NULL,
    team_id uuid NULL,
    name varchar(100) NOT NULL,
    description text NULL,
    status varchar(30) NOT NULL DEFAULT 'active',
    default_action varchar(20) NOT NULL DEFAULT 'allow',
    block_response varchar(30) NOT NULL DEFAULT 'nxdomain',
    security_enabled boolean NOT NULL DEFAULT true,
    adblock_enabled boolean NOT NULL DEFAULT false,
    parental_enabled boolean NOT NULL DEFAULT false,
    privacy_enabled boolean NOT NULL DEFAULT true,
    safe_search_enabled boolean NOT NULL DEFAULT false,
    log_mode varchar(30) NOT NULL DEFAULT 'full',
    current_version bigint NOT NULL DEFAULT 0,
    draft_version bigint NOT NULL DEFAULT 0,
    last_published_at timestamptz NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    deleted_at timestamptz NULL
);

CREATE TABLE IF NOT EXISTS profile_rules (
    id uuid PRIMARY KEY,
    profile_id uuid NOT NULL,
    list_type varchar(20) NOT NULL,
    match_type varchar(20) NOT NULL,
    domain varchar(255) NOT NULL,
    normalized_domain varchar(255) NOT NULL,
    action varchar(20) NOT NULL,
    category varchar(50) NULL,
    enabled boolean NOT NULL DEFAULT true,
    note text NULL,
    created_by uuid NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    deleted_at timestamptz NULL,
    CONSTRAINT chk_profile_rules_list_type CHECK (list_type IN ('allow', 'deny')),
    CONSTRAINT chk_profile_rules_match_type CHECK (match_type IN ('exact', 'suffix', 'wildcard'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_profile_rule_active
    ON profile_rules (profile_id, list_type, match_type, normalized_domain)
    WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS profile_feature_settings (
    id uuid PRIMARY KEY,
    profile_id uuid NOT NULL UNIQUE,
    security jsonb NOT NULL DEFAULT '{}'::jsonb,
    privacy jsonb NOT NULL DEFAULT '{}'::jsonb,
    parental jsonb NOT NULL DEFAULT '{}'::jsonb,
    preferences jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL
);

CREATE TABLE IF NOT EXISTS profile_versions (
    id uuid PRIMARY KEY,
    profile_id uuid NOT NULL,
    version bigint NOT NULL,
    status varchar(30) NOT NULL DEFAULT 'draft',
    checksum varchar(100) NOT NULL,
    config_json jsonb NOT NULL,
    rule_count integer NOT NULL DEFAULT 0,
    message varchar(255) NULL,
    published_by uuid NULL,
    external_publish_id varchar(80) NULL,
    published_at timestamptz NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_profile_versions_profile_version
    ON profile_versions (profile_id, version);
