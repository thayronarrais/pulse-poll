# Destacar a escolha de um participante (sob demanda)

Data: 2026-06-30
Tela: `admin/live-sessions/{slug}/control` (painel de controle ao vivo)

## Objetivo

Permitir que o apresentador veja facilmente as respostas de um participante
específico. Ao clicar num participante na lista "Participantes", cada card de
pergunta destaca qual alternativa essa pessoa escolheu. Clicar novamente (ou num
"×") limpa o destaque.

A atualização é **sob demanda** (revisão), não em tempo real: os dados são
carregados com a página e re-buscados quando um participante é selecionado.

## Contexto do código existente

- Votos (`LiveVote`) guardam `device_token`, `voter_name` e `choice`. Não há FK
  direta para o participante.
- Participantes (`LiveParticipant`) guardam `device_token`. A ligação voto →
  participante é feita por `device_token` dentro da sessão.
- O canal realtime `live-session.{slug}` é **público** (os votantes também
  assinam). Nada de voto individual ou `device_token` pode ser transmitido nele,
  para preservar o sigilo do voto.
- A página de controle já é autorizada pelo dono da sessão
  (`authorizeSession` em `LiveControlController`).

## Backend

### `LiveSession::participantResponses(): array`

Retorna o mapa `[participantId => [questionId => choice]]`.

- Carrega os votos das perguntas da sessão cujo `device_token` pertence a um
  participante identificado da sessão (uma query).
- Constrói um mapa `device_token => participantId` a partir dos participantes
  da sessão e usa-o para indexar o resultado por `participantId`.
- Participante sem voto numa pergunta simplesmente não tem entrada para aquela
  pergunta (o front trata como "não votou").

### Endpoint admin

`GET admin/live-sessions/{liveSession}/responses` →
`LiveControlController@responses`

- Autorizado por `authorizeSession` (dono da sessão); não-dono recebe 403.
- Responde JSON com o mapa de `participantResponses()`.
- Usado para re-buscar dados atualizados quando um participante é selecionado.

### `panel()`

Passa o mapa inicial (`participantResponses()`) no `$config`, para o destaque
funcionar sem precisar de fetch imediato.

## Frontend

`resources/js/live/control.js` + `resources/views/admin/live/control.blade.php`

### Estado novo

- `selectedParticipantId` (number | null)
- `responses` (mapa `participantId => { questionId: choice }`)
- `responsesUrl` (vindo do config)

### Comportamento

- `selectParticipant(p)`: alterna a seleção.
  - Ao selecionar: define `selectedParticipantId`, re-busca `responses` do
    endpoint (dados frescos).
  - Ao clicar no mesmo participante de novo: limpa a seleção.
- `pickFor(q)`: helper que retorna a escolha do participante selecionado para a
  pergunta `q` (ou `null`).
- Lista "Participantes": o participante selecionado ganha realce (anel/borda).
- Cada card de pergunta, quando há participante selecionado:
  - A alternativa escolhida por ele ganha realce (linha destacada + badge
    "✓ \<nome\> escolheu").
  - Se ele não votou naquela pergunta: badge "não votou".
- Botão/clique para limpar a seleção (ex.: clicar de novo no participante).

### Canal público

Sem mudanças. Nenhum voto individual é transmitido.

## Testes

`tests/Feature/LiveControlTest.php`

- O endpoint retorna o mapa correto `participante => pergunta => escolha`.
- Dono autorizado recebe 200 com os dados; não-dono recebe 403.
- Participante sem voto numa pergunta não aparece naquela pergunta no mapa.

## Fora de escopo (YAGNI)

- Atualização em tempo real do destaque (exigiria canal admin privado).
- Painel/tabela separada por participante (matriz geral).
- Exportação de respostas.
