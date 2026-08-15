import { createClient } from '@supabase/supabase-js'

const STORAGE_KEY = 'supabase_config'

let client = null

export function getSupabaseConfig() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export function saveSupabaseConfig(config) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(config))
}

export function clearSupabaseConfig() {
  localStorage.removeItem(STORAGE_KEY)
  client = null
}

export function getSupabaseClient() {
  if (client) return client
  const config = getSupabaseConfig()
  if (!config || !config.url || !config.anonKey) return null
  try {
    client = createClient(config.url, config.anonKey, {
      auth: { persistSession: false },
    })
    return client
  } catch {
    return null
  }
}

export function isSupabaseConfigured() {
  const config = getSupabaseConfig()
  return !!(config && config.url && config.anonKey)
}

export async function ensureFidapptiSchema() {
  const client = getSupabaseClient()
  if (!client) return { ok: true, skipped: true }
  try {
    const { error } = await client.rpc('ensure_fidappti_schema')
    if (error) {
      const msg = error.message || ''
      if (error.code === 'PGRST205' || /ensure_fidappti_schema/.test(msg) || /does not exist/.test(msg)) {
        return {
          ok: false,
          needsSetup: true,
          error: 'Supabase non inizializzato. Esegui una volta lo script di setup nell\'SQL Editor di Supabase (crea le tabelle e la funzione di auto-aggiornamento).',
        }
      }
      return { ok: false, error: msg }
    }
    return { ok: true }
  } catch (e) {
    return { ok: false, error: e.message }
  }
}

export async function testSupabaseConnection(url, anonKey) {
  try {
    const testClient = createClient(url, anonKey, { auth: { persistSession: false } })
    const { error: rpcErr } = await testClient.rpc('ensure_fidappti_schema')
    if (rpcErr && (rpcErr.code === 'PGRST205' || /ensure_fidappti_schema/.test(rpcErr.message || ''))) {
      return { ok: false, error: 'Setup non eseguito. Esegui lo script SQL aggiornato nell\'SQL Editor di Supabase: crea tabelle e funzione di auto-migrazione.' }
    }
    if (rpcErr) throw rpcErr
    const { error: selErr } = await testClient.from('cards').select('id', { count: 'exact', head: true })
    if (selErr && selErr.code === 'PGRST116') {
      return { ok: false, error: 'La tabella "cards" non è accessibile. Verifica le policy RLS.' }
    }
    if (selErr) throw selErr
    const { error: bcErr } = await testClient.from('business_cards').select('id', { count: 'exact', head: true })
    if (bcErr && bcErr.code === 'PGRST116') {
      return { ok: false, error: 'La tabella "business_cards" non è accessibile. Verifica le policy RLS.' }
    }
    if (bcErr) throw bcErr
    const testId = crypto.randomUUID()
    const { error: insErr } = await testClient.from('cards').insert({
      id: testId,
      store_name: '__test__',
      card_number: '0',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    })
    if (insErr) {
      if (insErr.code === '42501') {
        return { ok: false, error: 'La policy RLS blocca le scritture. Assicurati di aver eseguito lo script SQL di setup (include "create policy ... for all using (true) with check (true)").' }
      }
      if (insErr.code === '23502') {
        return { ok: false, error: 'La tabella ha una colonna "user_id" obbligatoria non prevista. Esegui lo script SQL aggiornato che la rimuove (alter table cards drop column if exists user_id).' }
      }
      throw insErr
    }
    await testClient.from('cards').delete().eq('id', testId)
    return { ok: true }
  } catch (e) {
    return { ok: false, error: e.message }
  }
}

export const SUPABASE_SETUP_SQL = `-- ==========================================
-- FidAPPti App - Schema Supabase
-- Incolla tutto nell'SQL Editor ed esegui
-- ==========================================

-- Estensioni
create extension if not exists "pgcrypto";

-- Rimuovi colonna user_id se presente (template default Supabase)
alter table cards drop column if exists user_id;

-- 1. Tabella carte fedeltà
create table if not exists cards (
  id uuid primary key default gen_random_uuid(),
  store_name text not null,
  card_number text not null,
  barcode_type text default 'CODE128',
  holder_name text,
  notes text,
  color text default '#1a73e8',
  logo_type text default 'predefined',
  logo_data text,
  is_favorite boolean default false,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

alter table cards enable row level security;

drop policy if exists "Enable all access for cards" on cards;
create policy "Enable all access for cards"
  on cards for all
  using (true)
  with check (true);

-- 2. Tabella biglietti da visita
create table if not exists business_cards (
  id uuid primary key default gen_random_uuid(),
  first_name text default '',
  last_name text default '',
  org text default '',
  role text default '',
  phone_personal text default '',
  phone_business text default '',
  email text default '',
  website text default '',
  address text default '',
  city text default '',
  province text default '',
  postal_code text default '',
  country text default '',
  notes text default '',
  color text default '#1a73e8',
  avatar_data text,
  is_favorite boolean default false,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

alter table business_cards enable row level security;

drop policy if exists "Enable all access for business_cards" on business_cards;
create policy "Enable all access for business_cards"
  on business_cards for all
  using (true)
  with check (true);

-- ==========================================
-- 3. Auto-migrazione schema
-- L'app chiama questa funzione via RPC ogni
-- volta che si sincronizza: crea/aggiorna le
-- tabelle mancanti in modo idempotente.
-- ==========================================
create or replace function ensure_fidappti_schema()
returns void
language plpgsql
security definer
set search_path = public
as $fn$
begin
  -- 1. Tabella carte fedeltà
  create table if not exists cards (
    id uuid primary key default gen_random_uuid(),
    store_name text not null,
    card_number text not null,
    barcode_type text default 'CODE128',
    holder_name text,
    notes text,
    color text default '#1a73e8',
    logo_type text default 'predefined',
    logo_data text,
    is_favorite boolean default false,
    created_at timestamptz default now(),
    updated_at timestamptz default now()
  );

  alter table cards drop column if exists user_id;

  alter table cards add column if not exists store_name text;
  alter table cards add column if not exists card_number text;
  alter table cards add column if not exists barcode_type text default 'CODE128';
  alter table cards add column if not exists holder_name text;
  alter table cards add column if not exists notes text;
  alter table cards add column if not exists color text default '#1a73e8';
  alter table cards add column if not exists logo_type text default 'predefined';
  alter table cards add column if not exists logo_data text;
  alter table cards add column if not exists is_favorite boolean default false;
  alter table cards add column if not exists created_at timestamptz default now();
  alter table cards add column if not exists updated_at timestamptz default now();

  alter table cards enable row level security;
  drop policy if exists "Enable all access for cards" on cards;
  create policy "Enable all access for cards"
    on cards for all
    using (true)
    with check (true);

  -- 2. Tabella biglietti da visita
  create table if not exists business_cards (
    id uuid primary key default gen_random_uuid(),
    first_name text default '',
    last_name text default '',
    org text default '',
    role text default '',
    phone_personal text default '',
    phone_business text default '',
    email text default '',
    website text default '',
    address text default '',
    city text default '',
    province text default '',
    postal_code text default '',
    country text default '',
    notes text default '',
    color text default '#1a73e8',
    avatar_data text,
    created_at timestamptz default now(),
    updated_at timestamptz default now()
  );

  alter table business_cards add column if not exists first_name text default '';
  alter table business_cards add column if not exists last_name text default '';
  alter table business_cards add column if not exists org text default '';
  alter table business_cards add column if not exists role text default '';
  alter table business_cards add column if not exists phone_personal text default '';
  alter table business_cards add column if not exists phone_business text default '';
  alter table business_cards add column if not exists email text default '';
  alter table business_cards add column if not exists website text default '';
  alter table business_cards add column if not exists address text default '';
  alter table business_cards add column if not exists city text default '';
  alter table business_cards add column if not exists postal_code text default '';
  alter table business_cards add column if not exists country text default '';
  alter table business_cards add column if not exists notes text default '';
  alter table business_cards add column if not exists color text default '#1a73e8';
  alter table business_cards add column if not exists avatar_data text;
  alter table business_cards add column if not exists is_favorite boolean default false;
  alter table business_cards add column if not exists created_at timestamptz default now();
  alter table business_cards add column if not exists updated_at timestamptz default now();

  alter table business_cards enable row level security;
  drop policy if exists "Enable all access for business_cards" on business_cards;
  create policy "Enable all access for business_cards"
    on business_cards for all
    using (true)
    with check (true);
end;
$fn$;

grant execute on function ensure_fidappti_schema() to anon, authenticated, service_role;
`
