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

export async function testSupabaseConnection(url, anonKey) {
  try {
    const testClient = createClient(url, anonKey, { auth: { persistSession: false } })
    const { error } = await testClient.from('cards').select('id', { count: 'exact', head: true })
    if (error && error.code !== 'PGRST116') throw error
    return { ok: true }
  } catch (e) {
    return { ok: false, error: e.message }
  }
}

export const SUPABASE_SETUP_SQL = `-- ==========================================
-- Carte Fedeltà App - Schema Supabase
-- Incolla tutto nell'SQL Editor ed esegui
-- ==========================================

-- Estensioni
create extension if not exists "pgcrypto";

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

create policy "Enable all access for cards"
  on cards for all
  using (true)
  with check (true);
`
