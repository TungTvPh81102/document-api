#!/bin/bash

# SSH Tunnel script to forward Supabase PostgreSQL
# ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
#     -L 5432:db.qhhwamenqysibcqcocrb.supabase.co:5432 \
#     ubuntu@<your-server-ip> -N

# For now, use this simpler approach with socat or ngrok if available
# Or modify .env to use connection pooler with correct credentials

# Alternative: Use psql to test connection
psql postgresql://postgres:123456789@db.qhhwamenqysibcqcocrb.supabase.co:5432/postgres -c "SELECT 1"
