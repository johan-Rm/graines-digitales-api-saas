# Gestion des secrets

- Ne pas committer de secret reel.
- Ne pas committer `.env.local`.
- Les cles JWT locales se generent avec :

```bash
docker compose -f docker-compose.dev.yml exec api php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

Les secrets de production doivent etre fournis par l'environnement cible.
