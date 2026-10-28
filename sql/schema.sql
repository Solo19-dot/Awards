-- Run this whole file in Supabase: SQL Editor > New query > Run

create table categories (
  id          bigint generated always as identity primary key,
  name        text not null,
  description text,
  sort_order  int  not null default 0,
  is_active   boolean not null default true,
  created_at  timestamptz not null default now()
);

create table nominees (
  id          bigint generated always as identity primary key,
  category_id bigint not null references categories(id) on delete cascade,
  name        text not null,
  bio         text,
  photo_path  text,
  created_at  timestamptz not null default now()
);

create table settings (
  key   text primary key,
  value text not null
);
insert into settings (key, value) values
  ('voting_open', 'false'),
  ('results_public', 'false')
on conflict (key) do nothing;

-- One row per ballot (pending until PayChangu confirms payment)
create table payments (
  id         bigint generated always as identity primary key,
  tx_ref     text not null unique,
  amount     numeric not null,
  currency   text not null default 'MWK',
  status     text not null default 'pending'
             check (status in ('pending','paid','failed')),
  created_at timestamptz not null default now(),
  paid_at    timestamptz
);

-- The picks on a ballot: at most one nominee per category per payment
create table vote_items (
  id          bigint generated always as identity primary key,
  payment_id  bigint not null references payments(id) on delete cascade,
  category_id bigint not null references categories(id),
  nominee_id  bigint not null references nominees(id),
  unique (payment_id, category_id)
);
create index vote_items_nominee_idx on vote_items(nominee_id);

-- Vote totals: only ballots with a verified payment are counted
create view results with (security_invoker = on) as
select n.id as nominee_id,
       n.category_id,
       n.name,
       count(p.id) as total_votes
from nominees n
left join vote_items v on v.nominee_id = n.id
left join payments   p on p.id = v.payment_id and p.status = 'paid'
group by n.id, n.category_id, n.name;

-- Lock everything: only your PHP server (service key) can read or write
alter table categories enable row level security;
alter table nominees   enable row level security;
alter table settings   enable row level security;
alter table payments   enable row level security;
alter table vote_items enable row level security;
revoke all on results from anon, authenticated;
