# Coleção Postman — ViPP

| Arquivo | Conteúdo |
|---|---|
| `ViPP.postman_collection.json` | 6 requisições com testes: postagem válida, postagem com etiqueta informada, campos obrigatórios (400), DV da etiqueta (400/2002), data da NF (400/1500), credenciais (401/1030). |
| `Homologacao.postman_environment.json` | `baseUrl`, `usuario`, `token`, `idPerfil` (credenciais públicas de homologação) e `etiquetaInformada`. |
| `gerar-colecao.php` | Gera os dois arquivos a partir dos DTOs do SDK. **Não edite os JSON à mão**: altere o script e rode de novo. |

> As requisições 01 e 02 criam postagens reais no ambiente configurado. Use a homologação.

## Rodar

```bash
php postman/gerar-colecao.php     # regenerar após mudar o SDK

# Postman CLI (não precisa de login para rodar localmente)
postman collection run postman/ViPP.postman_collection.json -e postman/Homologacao.postman_environment.json

# Outro perfil, sem gravar credenciais em arquivo
postman collection run postman/ViPP.postman_collection.json -e postman/Homologacao.postman_environment.json \
  --env-var usuario=... --env-var token=... --env-var idPerfil=...
```

Para usar credenciais reais na interface do Postman, duplique o ambiente com o sufixo `.local.postman_environment.json` (ignorado pelo Git).
