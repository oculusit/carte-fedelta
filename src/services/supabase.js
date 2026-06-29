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
    const { error: selErr } = await testClient.from('cards').select('id', { count: 'exact', head: true })
    if (selErr && selErr.code === 'PGRST116') {
      return { ok: false, error: 'La tabella "cards" non esiste. Vai su SQL Editor ed esegui lo script di setup.' }
    }
    if (selErr) throw selErr
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
-- Carte Fedeltà App - Schema Supabase
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
`
