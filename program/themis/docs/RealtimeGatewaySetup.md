# Realtime Gateway Setup (Ubuntu + Node.js)

This guide sets up a persistent websocket channel for near-realtime combat updates.

## Architecture

- Browser JS clients connect to Node Socket.IO gateway.
- Node calls PHP service endpoint: `/themis/combat_realtime_api.php`.
- PHP remains combat authority (`CombatService`) and DB writer.
- Node broadcasts refreshed state to all clients in the combat room.

## New files

- `/var/www/realtime-gateway/server.js`
- `/var/www/realtime-gateway/.env.example`
- `/var/www/realtime-gateway/package.json`
- `/var/www/realtime-gateway/systemd/themis-realtime.service`
- `/var/www/realtime-gateway/nginx/themis-realtime.conf.snippet`
- `/var/www/html/themis/combat_realtime_api.php`

## 1. Install Node.js

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

## 2. Install gateway dependencies

```bash
cd /var/www/realtime-gateway
cp .env.example .env
npm install
```

Set a long random token in `/var/www/realtime-gateway/.env`:

```env
THEMIS_REALTIME_TOKEN=replace-with-a-long-random-secret
```

## 3. Make token available to PHP runtime

`combat_realtime_api.php` reads `THEMIS_REALTIME_TOKEN` via `getenv()`.

### Option A: Apache + mod_php (common on this repo)

Add `SetEnv` in your Apache vhost (or a conf snippet):

```apache
SetEnv THEMIS_REALTIME_TOKEN "replace-with-a-long-random-secret"
```

Example file:

```bash
sudo nano /etc/apache2/sites-available/000-default.conf
```

Then reload Apache:

```bash
sudo systemctl reload apache2
```

### Option B: PHP-FPM

If you are using PHP-FPM, add in pool config:

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
env[THEMIS_REALTIME_TOKEN] = replace-with-a-long-random-secret
```

Then reload FPM:

```bash
sudo systemctl reload php8.3-fpm
```

## 4. Run gateway

Development:

```bash
cd /var/www/realtime-gateway
npm run dev
```

Production (systemd):

```bash
sudo cp /var/www/realtime-gateway/systemd/themis-realtime.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now themis-realtime
```

## 5. Nginx proxy

Add websocket location from:

- `/var/www/realtime-gateway/nginx/themis-realtime.conf.snippet`

Then:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 6. Browser integration sketch

```javascript
const socket = io('/', {
  path: '/socket.io',
  auth: { playerUuid }
});

socket.emit('combat:join_instance', { instanceId }, (reply) => {
  if (reply.ok) renderState(reply.state);
});

socket.on('combat:state_updated', (state) => {
  renderState(state);
});

socket.emit('combat:submit_action', {
  instanceId,
  actionType,
  targetPlayerUuid,
  payload: {
    attack_kind: 'physical',
    power: 7,
    lock_in: true
  }
});
```

## Why this is beginner-friendly

- You keep all combat logic in PHP where it already exists.
- Node only handles realtime transport and room fanout.
- You can test locally first, then harden auth/rate limits incrementally.
