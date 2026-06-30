# Stage 882 Live Config Deploy Protection

## Reason

Live deployment showed the app code updated correctly, but the generated `main.zip` deployment overwrote the server-side private DB config with the sanitized repo placeholder config.

## Change

`.gitattributes` now keeps the tracked sanitized config files in the repository while excluding them from generated GitHub deploy archives:

```text
/config.php export-ignore
/labs/config.php export-ignore
```

This means future `main.zip` downloads should not include those two files and should not overwrite the live private DB credentials during cPanel deploys.

## Repo behavior

- `config.php` remains tracked in GitHub as a sanitized placeholder reference.
- `labs/config.php` remains tracked in GitHub as a sanitized placeholder reference.
- Deploy archives omit both files.
- First-time installs should copy from `config-example.php` or `labs/config-example.php` and fill credentials on the server.

## Safety boundaries

- No live credentials committed.
- No SQL.
- No config path moved.
- No hard auth gates forced.
- No payment processing.
- No wallet mutation.
- No production claim/redeem mutation.
- No destructive Microgifter sync.
