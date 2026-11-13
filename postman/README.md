# Coleção Postman — ViPP

| Arquivo | Conteúdo |
|---|---|
| `ViPP.postman_collection.json` | 9 requisições com testes: postagem válida, postagem com etiqueta informada, campos obrigatórios (400), DV da etiqueta (400/2002), data da NF (400/1500), credenciais (401/1030), cartão sem contrato (400/1100), exclusão sem objeto elegível (200, nada excluído) e exclusão com token errado (401/1030). As postagens válidas enviam `ContratoEct`, exigido em produção. As requisições de exclusão usam uma etiqueta inexistente e nunca excluem nada. |
| `Homologacao.postman_environment.json` | `baseUrl`, `usuario`, `token`, `idPerfil` (credenciais públicas de homologação), `nrContrato` e `nrCartao` (fictícios: a homologação usa o contrato do perfil) e `etiquetaInformada`. |
| `gerar-colecao.php` | Gera os dois arquivos a partir dos DTOs do SDK. **Não edite os JSON à mão**: altere o script e rode de novo. |

> As requisições 01 e 02 criam postagens reais no ambiente configurado. Use a homologação.

## Rodar

```bash
php postman/gerar-colecao.php     # regenerar após mudar o SDK

# Postman CLI (não precisa de login para rodar localmente)
postman collection run postman/ViPP.postman_collection.json -e postman/Homologacao.postman_environment.json

# Outro perfil, sem gravar credenciais em arquivo
postman collection run postman/ViPP.postman_collection.json -e postman/Homologacao.postman_environment.json \
  --env-var usuario=... --env-var token=... --env-var idPerfil=... --env-var nrContrato=... --env-var nrCartao=...
```

Para usar credenciais reais na interface do Postman, duplique o ambiente com o sufixo `.local.postman_environment.json` (ignorado pelo Git).
