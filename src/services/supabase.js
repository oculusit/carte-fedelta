import { createClient } from '@supabase/supabase-js'
import { settingsDb } from './db.js'

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
      auth: { persistSession: true, storage: localStorage },
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
  user_id uuid references auth.users not null default auth.uid(),
  store_name text not null,
  card_number text not null,
  barcode_type text default 'CODE128',
  holder_name text,
  notes text,
  color text default '#1a73e8',
  logo_type text default 'predefined',
  logo_data text,
  is_private boolean default false,
  is_favorite boolean default false,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

alter table cards enable row level security;

create policy "Users can manage own cards"
  on cards for all
  using (auth.uid() = user_id)
  with check (auth.uid() = user_id);

-- 2. Tabella gruppi famiglia
create table if not exists family_groups (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  owner_id uuid references auth.users not null default auth.uid(),
  created_at timestamptz default now()
);

alter table family_groups enable row level security;

create policy "Users can manage own groups"
  on family_groups for all
  using (auth.uid() = owner_id)
  with check (auth.uid() = owner_id);

-- 3. Tabella membri famiglia
create table if not exists family_members (
  id uuid primary key default gen_random_uuid(),
  group_id uuid references family_groups(id) on delete cascade,
  user_id uuid references auth.users not null,
  email text,
  status text default 'pending',
  created_at timestamptz default now()
);

alter table family_members enable row level security;

create policy "Members can view own membership"
  on family_members for select
  using (auth.uid() = user_id);

create policy "Group owners can manage members"
  on family_members for all
  using (auth.uid() in (
    select owner_id from family_groups where id = group_id
  ))
  with check (auth.uid() in (
    select owner_id from family_groups where id = group_id
  ));

-- 4. Indici per performance
create index if not exists cards_user_id_idx on cards(user_id);
create index if not exists cards_store_name_idx on cards(store_name);
create index if not exists family_members_group_id_idx on family_members(group_id);
create index if not exists family_members_user_id_idx on family_members(user_id);
`
