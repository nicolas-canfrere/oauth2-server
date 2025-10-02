-- OAuth2 Server Database Schema
-- Generated from Doctrine migrations

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Table: oauth_clients
CREATE TABLE IF NOT EXISTS oauth_clients (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    client_id VARCHAR(255) NOT NULL,
    client_secret_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    redirect_uri VARCHAR(255) NOT NULL,
    grant_types JSONB NOT NULL DEFAULT '[]',
    scopes JSONB NOT NULL DEFAULT '[]',
    is_confidential BOOLEAN DEFAULT true,
    pkce_required BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_clients_client_id ON oauth_clients (client_id);

-- Table: oauth_authorization_codes
CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    id UUID PRIMARY KEY,
    code_hash VARCHAR(255) NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    redirect_uri VARCHAR(255) NOT NULL,
    scopes JSONB NOT NULL DEFAULT '[]',
    code_challenge VARCHAR(255),
    code_challenge_method VARCHAR(255),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_authorization_codes_code_hash
    ON oauth_authorization_codes (code_hash);

CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_expires_at
    ON oauth_authorization_codes (expires_at);

-- Table: oauth_refresh_tokens
CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
    id UUID PRIMARY KEY,
    token_hash VARCHAR(255) NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    scopes JSONB,
    is_revoked BOOLEAN DEFAULT false,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_token_hash ON oauth_refresh_tokens (token_hash);
CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_client_user_revoked ON oauth_refresh_tokens (client_id, user_id, is_revoked);
CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_expires_at ON oauth_refresh_tokens (expires_at);

-- Table: oauth_token_blacklist
CREATE TABLE IF NOT EXISTS oauth_token_blacklist (
    id UUID PRIMARY KEY,
    jti VARCHAR(255) NOT NULL,
    reason TEXT,
    is_revoked BOOLEAN DEFAULT true,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_token_blacklist_jti ON oauth_token_blacklist (jti);
CREATE INDEX IF NOT EXISTS idx_oauth_token_blacklist_expires_at ON oauth_token_blacklist (expires_at);

-- Table: oauth_scopes
CREATE TABLE IF NOT EXISTS oauth_scopes (
    id UUID PRIMARY KEY,
    scope VARCHAR(255) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_scopes_scope ON oauth_scopes (scope);

-- Table: users
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    totp_secret VARCHAR(255),
    is_2fa_enabled BOOLEAN DEFAULT false,
    updated_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email);

-- Table: user_consents
CREATE TABLE IF NOT EXISTS user_consents (
    id UUID PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    scopes JSONB NOT NULL DEFAULT '[]',
    granted_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_user_consents_user_client ON user_consents (user_id, client_id);

-- Table: oauth_keys
CREATE TABLE IF NOT EXISTS oauth_keys (
    id UUID PRIMARY KEY,
    kid VARCHAR(255) NOT NULL,
    algorithm VARCHAR(255) NOT NULL,
    public_key TEXT NOT NULL,
    private_key_encrypted TEXT NOT NULL,
    is_active BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_keys_kid ON oauth_keys (kid);
CREATE INDEX IF NOT EXISTS idx_oauth_keys_active_created ON oauth_keys (is_active, created_at);

-- Composite indexes for frequent queries
CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_client_user_expires
    ON oauth_authorization_codes (client_id, user_id, expires_at);

CREATE INDEX IF NOT EXISTS idx_oauth_clients_confidential_pkce
    ON oauth_clients (is_confidential, pkce_required);

-- Partial indexes for active records only (PostgreSQL optimization)
CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_active
    ON oauth_refresh_tokens (user_id, client_id, expires_at)
    WHERE is_revoked = false;

CREATE INDEX IF NOT EXISTS idx_oauth_keys_active
    ON oauth_keys (kid, expires_at)
    WHERE is_active = true;