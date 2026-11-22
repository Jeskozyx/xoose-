-- ============================================================================
-- XOOSE–LOVEYOU CENTRALIZED REMOTE SERVICE MANAGER
-- Supabase PostgreSQL Control Plane Schema (10 Tables) & Row Level Security
-- ============================================================================
-- Catatan Keamanan:
-- 1. Role `anon` TIDAK diberi akses baca/tulis ke tabel control plane mana pun.
-- 2. Laravel Xoose backend (server-side) menggunakan `service_role` key yang
--    TIDAK PERNAH ditanamkan ke .exe atau client config.ini.
-- 3. Admin terautentikasi (`authenticated`) hanya dapat mengelola resource
--    milik `owner_user_id = auth.uid()`.
-- 4. Device Agent yang memakai scoped JWT (`auth.jwt() ->> 'device_uuid'`)
--    hanya dapat membaca command/config/release miliknya sendiri dan melaporkan
--    status/heartbeat/log miliknya sendiri.
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ----------------------------------------------------------------------------
-- 1. managed_apps
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.managed_apps (
    id              BIGSERIAL PRIMARY KEY,
    app_project_id  BIGINT NULL,
    owner_user_id   UUID NULL,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL UNIQUE,
    description     TEXT NULL,
    current_version VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    build_status    VARCHAR(32) NOT NULL DEFAULT 'idle',
    default_config  JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ----------------------------------------------------------------------------
-- 2. app_devices
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.app_devices (
    id              BIGSERIAL PRIMARY KEY,
    device_uuid     UUID NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    managed_app_id  BIGINT NOT NULL REFERENCES public.managed_apps(id) ON DELETE CASCADE,
    device_code     VARCHAR(64) NOT NULL UNIQUE,
    hostname        VARCHAR(255) NOT NULL DEFAULT 'Unknown',
    os_info         VARCHAR(255) NULL,
    app_version     VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    status          VARCHAR(24) NOT NULL DEFAULT 'Pending'
                        CHECK (status IN ('Online', 'Offline', 'Pending', 'Revoked')),
    token_hash      VARCHAR(64) NULL UNIQUE,
    last_ip         VARCHAR(45) NULL,
    revoked_at      TIMESTAMPTZ NULL,
    last_seen_at    TIMESTAMPTZ NULL,
    metadata        JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_app_devices_app_status ON public.app_devices (managed_app_id, status);
CREATE INDEX IF NOT EXISTS idx_app_devices_last_seen ON public.app_devices (last_seen_at);

-- ----------------------------------------------------------------------------
-- 3. device_enrollments
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.device_enrollments (
    id                  BIGSERIAL PRIMARY KEY,
    managed_app_id      BIGINT NOT NULL REFERENCES public.managed_apps(id) ON DELETE CASCADE,
    enrollment_code     VARCHAR(64) NOT NULL UNIQUE,
    label               VARCHAR(255) NULL,
    status              VARCHAR(24) NOT NULL DEFAULT 'pending'
                            CHECK (status IN ('pending', 'used', 'expired', 'revoked')),
    max_uses            INTEGER NOT NULL DEFAULT 1 CHECK (max_uses >= 1),
    uses_count          INTEGER NOT NULL DEFAULT 0 CHECK (uses_count >= 0),
    expires_at          TIMESTAMPTZ NULL,
    enrolled_device_id  BIGINT NULL REFERENCES public.app_devices(id) ON DELETE SET NULL,
    created_by          UUID NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ----------------------------------------------------------------------------
-- 4. service_definitions
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.service_definitions (
    id                      BIGSERIAL PRIMARY KEY,
    service_key             VARCHAR(64) NOT NULL UNIQUE,
    display_name            VARCHAR(255) NOT NULL,
    description             TEXT NULL,
    supported_actions       JSONB NOT NULL DEFAULT '[]'::jsonb,
    configuration_schema    JSONB NOT NULL DEFAULT '{}'::jsonb,
    permission_requirements JSONB NOT NULL DEFAULT '[]'::jsonb,
    is_system               BOOLEAN NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ----------------------------------------------------------------------------
-- 5. service_commands
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.service_commands (
    id              BIGSERIAL PRIMARY KEY,
    command_id      UUID NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    device_id       BIGINT NOT NULL REFERENCES public.app_devices(id) ON DELETE CASCADE,
    service_key     VARCHAR(64) NOT NULL,
    action          VARCHAR(32) NOT NULL
                        CHECK (action IN ('start', 'stop', 'pause', 'resume', 'restart', 'apply_config')),
    payload         JSONB NOT NULL DEFAULT '{}'::jsonb,
    status          VARCHAR(24) NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending', 'delivered', 'acknowledged', 'succeeded', 'failed', 'expired', 'cancelled')),
    issued_by       UUID NULL,
    expires_at      TIMESTAMPTZ NOT NULL,
    delivered_at    TIMESTAMPTZ NULL,
    acknowledged_at TIMESTAMPTZ NULL,
    completed_at    TIMESTAMPTZ NULL,
    result_message  TEXT NULL,
    result_payload  JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_service_commands_device_status ON public.service_commands (device_id, status);
CREATE INDEX IF NOT EXISTS idx_service_commands_expires_at ON public.service_commands (expires_at);

-- ----------------------------------------------------------------------------
-- 6. service_configs
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.service_configs (
    id                  BIGSERIAL PRIMARY KEY,
    managed_app_id      BIGINT NOT NULL REFERENCES public.managed_apps(id) ON DELETE CASCADE,
    device_id           BIGINT NULL REFERENCES public.app_devices(id) ON DELETE CASCADE,
    service_key         VARCHAR(64) NOT NULL,
    config_version      INTEGER NOT NULL CHECK (config_version >= 1),
    config_json         JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    force_apply         BOOLEAN NOT NULL DEFAULT FALSE,
    updated_by          UUID NULL,
    previous_version_id BIGINT NULL REFERENCES public.service_configs(id) ON DELETE SET NULL,
    change_notes        VARCHAR(255) NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_service_configs_scope_active
    ON public.service_configs (managed_app_id, device_id, service_key, is_active);

-- ----------------------------------------------------------------------------
-- 7. service_status
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.service_status (
    id                    BIGSERIAL PRIMARY KEY,
    device_id             BIGINT NOT NULL REFERENCES public.app_devices(id) ON DELETE CASCADE,
    service_key           VARCHAR(64) NOT NULL,
    current_status        VARCHAR(24) NOT NULL DEFAULT 'Unknown'
                              CHECK (current_status IN ('Running', 'Stopped', 'Paused', 'Failed', 'Unknown')),
    active_config_version INTEGER NOT NULL DEFAULT 0,
    details               JSONB NOT NULL DEFAULT '{}'::jsonb,
    reported_at           TIMESTAMPTZ NULL,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (device_id, service_key)
);

-- ----------------------------------------------------------------------------
-- 8. device_heartbeats
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.device_heartbeats (
    id               BIGSERIAL PRIMARY KEY,
    device_id        BIGINT NOT NULL REFERENCES public.app_devices(id) ON DELETE CASCADE,
    app_version      VARCHAR(32) NULL,
    agent_uptime     DOUBLE PRECISION NOT NULL DEFAULT 0,
    ip_address       VARCHAR(45) NULL,
    services_summary JSONB NOT NULL DEFAULT '{}'::jsonb,
    received_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_device_heartbeats_device_time ON public.device_heartbeats (device_id, received_at DESC);

-- ----------------------------------------------------------------------------
-- 9. service_logs
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.service_logs (
    id              BIGSERIAL PRIMARY KEY,
    managed_app_id  BIGINT NULL REFERENCES public.managed_apps(id) ON DELETE CASCADE,
    device_id       BIGINT NULL REFERENCES public.app_devices(id) ON DELETE CASCADE,
    actor_user_id   UUID NULL,
    service_key     VARCHAR(64) NOT NULL DEFAULT 'system',
    level           VARCHAR(16) NOT NULL DEFAULT 'info'
                        CHECK (level IN ('debug', 'info', 'warning', 'error', 'critical')),
    event_type      VARCHAR(48) NOT NULL DEFAULT 'agent_event',
    message         TEXT NOT NULL,
    context         JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_service_logs_created_at ON public.service_logs (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_service_logs_device_service ON public.service_logs (device_id, service_key);

-- ----------------------------------------------------------------------------
-- 10. app_releases
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.app_releases (
    id              BIGSERIAL PRIMARY KEY,
    managed_app_id  BIGINT NOT NULL REFERENCES public.managed_apps(id) ON DELETE CASCADE,
    version         VARCHAR(32) NOT NULL,
    artifact_path   VARCHAR(500) NOT NULL,
    artifact_url    VARCHAR(1000) NULL,
    sha256_checksum VARCHAR(64) NOT NULL,
    signature       VARCHAR(128) NULL,
    release_notes   TEXT NULL,
    is_active       BOOLEAN NOT NULL DEFAULT FALSE,
    published_by    UUID NULL,
    published_at    TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (managed_app_id, version)
);

-- ============================================================================
-- ENABLE ROW LEVEL SECURITY (RLS) ON ALL 10 CONTROL PLANE TABLES
-- ============================================================================
ALTER TABLE public.managed_apps        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.app_devices         ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.device_enrollments  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_definitions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_commands    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_configs     ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_status      ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.device_heartbeats   ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_logs        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.app_releases        ENABLE ROW LEVEL SECURITY;

-- Cabut akses role `anon` secara eksplisit
REVOKE ALL ON public.managed_apps, public.app_devices, public.device_enrollments,
    public.service_definitions, public.service_commands, public.service_configs,
    public.service_status, public.device_heartbeats, public.service_logs,
    public.app_releases FROM anon;

-- ============================================================================
-- HELPER FUNCTION UNTUK CEK KEPEMILIKAN ADMIN & KLAIM DEVICE JWT
-- ============================================================================
CREATE OR REPLACE FUNCTION public.current_agent_device_uuid()
RETURNS UUID
LANGUAGE SQL
STABLE
AS $$
    SELECT NULLIF(auth.jwt() ->> 'device_uuid', '')::uuid;
$$;

-- ============================================================================
-- RLS POLICIES: ADMIN OWNER ISOLATION (authenticated) & DEVICE SCOPING
-- ============================================================================

-- 1. managed_apps
DROP POLICY IF EXISTS "admin_manage_own_apps" ON public.managed_apps;
CREATE POLICY "admin_manage_own_apps"
    ON public.managed_apps
    FOR ALL
    TO authenticated
    USING (owner_user_id = auth.uid())
    WITH CHECK (owner_user_id = auth.uid());

-- 2. app_devices
DROP POLICY IF EXISTS "admin_manage_own_devices" ON public.app_devices;
CREATE POLICY "admin_manage_own_devices"
    ON public.app_devices
    FOR ALL
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = app_devices.managed_app_id AND a.owner_user_id = auth.uid()
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = app_devices.managed_app_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_read_self" ON public.app_devices;
CREATE POLICY "device_read_self"
    ON public.app_devices
    FOR SELECT
    TO authenticated
    USING (device_uuid = public.current_agent_device_uuid() AND revoked_at IS NULL);

-- 3. device_enrollments (khusus admin pemilik app)
DROP POLICY IF EXISTS "admin_manage_own_enrollments" ON public.device_enrollments;
CREATE POLICY "admin_manage_own_enrollments"
    ON public.device_enrollments
    FOR ALL
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = device_enrollments.managed_app_id AND a.owner_user_id = auth.uid()
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = device_enrollments.managed_app_id AND a.owner_user_id = auth.uid()
        )
    );

-- 4. service_definitions (katalog layanan dapat dibaca oleh authenticated admin/agent)
DROP POLICY IF EXISTS "authenticated_read_service_definitions" ON public.service_definitions;
CREATE POLICY "authenticated_read_service_definitions"
    ON public.service_definitions
    FOR SELECT
    TO authenticated
    USING (TRUE);

-- 5. service_commands
DROP POLICY IF EXISTS "admin_manage_own_commands" ON public.service_commands;
CREATE POLICY "admin_manage_own_commands"
    ON public.service_commands
    FOR ALL
    TO authenticated
    USING (
        EXISTS (
            SELECT 1
            FROM public.app_devices d
            JOIN public.managed_apps a ON a.id = d.managed_app_id
            WHERE d.id = service_commands.device_id AND a.owner_user_id = auth.uid()
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1
            FROM public.app_devices d
            JOIN public.managed_apps a ON a.id = d.managed_app_id
            WHERE d.id = service_commands.device_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_read_and_ack_own_commands" ON public.service_commands;
CREATE POLICY "device_read_and_ack_own_commands"
    ON public.service_commands
    FOR SELECT
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.id = service_commands.device_id
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    );

-- 6. service_configs
DROP POLICY IF EXISTS "admin_manage_own_configs" ON public.service_configs;
CREATE POLICY "admin_manage_own_configs"
    ON public.service_configs
    FOR ALL
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = service_configs.managed_app_id AND a.owner_user_id = auth.uid()
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = service_configs.managed_app_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_read_own_configs" ON public.service_configs;
CREATE POLICY "device_read_own_configs"
    ON public.service_configs
    FOR SELECT
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.managed_app_id = service_configs.managed_app_id
              AND (service_configs.device_id IS NULL OR service_configs.device_id = d.id)
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    );

-- 7. service_status
DROP POLICY IF EXISTS "admin_read_own_service_status" ON public.service_status;
CREATE POLICY "admin_read_own_service_status"
    ON public.service_status
    FOR SELECT
    TO authenticated
    USING (
        EXISTS (
            SELECT 1
            FROM public.app_devices d
            JOIN public.managed_apps a ON a.id = d.managed_app_id
            WHERE d.id = service_status.device_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_upsert_own_service_status" ON public.service_status;
CREATE POLICY "device_upsert_own_service_status"
    ON public.service_status
    FOR ALL
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.id = service_status.device_id
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.id = service_status.device_id
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    );

-- 8. device_heartbeats
DROP POLICY IF EXISTS "admin_read_own_heartbeats" ON public.device_heartbeats;
CREATE POLICY "admin_read_own_heartbeats"
    ON public.device_heartbeats
    FOR SELECT
    TO authenticated
    USING (
        EXISTS (
            SELECT 1
            FROM public.app_devices d
            JOIN public.managed_apps a ON a.id = d.managed_app_id
            WHERE d.id = device_heartbeats.device_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_insert_own_heartbeats" ON public.device_heartbeats;
CREATE POLICY "device_insert_own_heartbeats"
    ON public.device_heartbeats
    FOR INSERT
    TO authenticated
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.id = device_heartbeats.device_id
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    );

-- 9. service_logs
DROP POLICY IF EXISTS "admin_read_own_logs" ON public.service_logs;
CREATE POLICY "admin_read_own_logs"
    ON public.service_logs
    FOR SELECT
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = service_logs.managed_app_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_insert_own_logs" ON public.service_logs;
CREATE POLICY "device_insert_own_logs"
    ON public.service_logs
    FOR INSERT
    TO authenticated
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.id = service_logs.device_id
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    );

-- 10. app_releases
DROP POLICY IF EXISTS "admin_manage_own_releases" ON public.app_releases;
CREATE POLICY "admin_manage_own_releases"
    ON public.app_releases
    FOR ALL
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = app_releases.managed_app_id AND a.owner_user_id = auth.uid()
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.managed_apps a
            WHERE a.id = app_releases.managed_app_id AND a.owner_user_id = auth.uid()
        )
    );

DROP POLICY IF EXISTS "device_read_own_active_releases" ON public.app_releases;
CREATE POLICY "device_read_own_active_releases"
    ON public.app_releases
    FOR SELECT
    TO authenticated
    USING (
        is_active = TRUE
        AND EXISTS (
            SELECT 1 FROM public.app_devices d
            WHERE d.managed_app_id = app_releases.managed_app_id
              AND d.device_uuid = public.current_agent_device_uuid()
              AND d.revoked_at IS NULL
        )
    );
