# Coleção Postman — ViPP

| Arquivo | Conteúdo |
|---|---|
| `ViPP.postman_collection.json` | 11 requisições com testes (as 10 e 11 são a consulta de situação da postagem criada na 01 e o 401 de credenciais; **rode a coleção inteira, na ordem**, porque a 10 usa a etiqueta que a 01 guarda). As 9 primeiras: postagem válida, postagem com etiqueta informada, campos obrigatórios (400), DV da etiqueta (400/2002), data da NF (400/1500), credenciais (401/1030), cartão sem contrato (400/1100), exclusão sem objeto elegível (200, nada excluído) e exclusão com token errado (401/1030). As postagens válidas enviam `ContratoEct`, exigido em produção. As requisições de exclusão usam uma etiqueta inexistente e nunca excluem nada. |
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

## Limitação: consulta sem resultado (204)

O ViPP responde a consulta sem resultado (`GetSituacaoPostagem` com etiqueta inexistente, o que o SDK usa em `verificarCredenciais()`) com `204 No Content`, mas com o cabeçalho `Content-Length: 163` e sem corpo. É uma resposta mal formada: o cURL tolera, o parser do Postman falha com `Parse Error: Expected HTTP/, RTSP/ or ICE/`. Por isso a coleção não tem essa requisição. No SDK, o teste de integração `verificarCredenciais aprova as credenciais da homologação` cobre o caso.
