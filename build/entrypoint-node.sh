#!/bin/bash
set -e

cd /app

has_react_start() {
  [ -f package.json ] && node -e 'const p=require("/app/package.json"); process.exit(p?.scripts?.start ? 0 : 1)'
}

bootstrap_react() {
  echo "Bootstrapping React app in /app..."
  tmp_app_dir="/tmp/cra-bootstrap-$(date +%s)-$$"
  mkdir -p "$tmp_app_dir"
  npx -y create-react-app "$tmp_app_dir" --use-npm --skip-git

  # Keep .gitkeep (if present) but reset all other files to avoid stale/broken partial scaffolds.
  find /app -mindepth 1 -maxdepth 1 ! -name '.gitkeep' -exec rm -rf {} +
  cp -a "$tmp_app_dir"/. /app/
  rm -rf "$tmp_app_dir"
}

if ! has_react_start; then
  bootstrap_react
fi

[ -d node_modules ] || npm install
exec npm start
