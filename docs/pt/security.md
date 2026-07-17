# 🔐 Segurança e Conformidade

- Controle de acesso baseado em capabilities
- Validação no servidor do tempo de recarga e limites
- Proteção com `require_sesskey()`
- Compatível com a API externa do Moodle
- Participação no ranking com controle de privacidade

## 🔎 Divulgação de Serviço de Terceiros

O PlayerHUD inclui recursos opcionais de IA: um **Gerador de Conteúdo** (itens, capítulos, backstories de classes) e um **Assistente Game Master** (chat conversacional para professores que também pode acionar ações no jogo).

### O recurso de IA é obrigatório?

Não. O plugin funciona de forma completa sem qualquer serviço externo.
Todo o conteúdo pode ser criado manualmente dentro do Moodle.
Os recursos de IA são ferramentas de produtividade — o assistente exige confirmação antes de salvar qualquer coisa.

### Cadeia de Provedores de IA

O PlayerHUD seleciona o provedor de IA **nível por nível**, seguindo a escada compartilhada do ecossistema PlayerGames. Uma chave explicitamente configurada sempre prevalece sobre o padrão institucional; o `core_ai` fica na base.

**Escada de resolução (maior prioridade primeiro):**

| Nível | Origem |
|-------|--------|
| 1 | **Chave pessoal própria** — chave do professor cadastrada no PlayerHUD (aba *Configurações* → Chaves de API) |
| 2 | **Chave pessoal do hub** — chave do professor cadastrada no **local_aihub** (se instalado) |
| 3 | **Chave de site própria** — chave cadastrada pelo admin nas configurações do PlayerHUD |
| 4 | **Chave de site do hub** — chave cadastrada pelo admin nas configurações do **local_aihub** (se instalado) |
| 5 | **Moodle `core_ai`** — provedores configurados em *Administração do site → IA → Provedores de IA*. Nenhuma chave armazenada no PlayerHUD. |

**Nível primeiro, não provedor primeiro.** Cada nível acima é avaliado como um todo: o primeiro nível que contiver *qualquer* chave é usado exclusivamente. Assim, a chave pessoal do professor (nível 1) sempre prevalece sobre uma chave do hub (nível 2) — mesmo que a chave do hub seja de um provedor de maior prioridade. Por exemplo, a chave de endpoint personalizado do professor não é substituída por uma chave Gemini que esteja no hub. O `core_ai` é consultado apenas quando nenhum nível possui uma chave.

**Dentro do nível escolhido**, os provedores diretos são testados na ordem Gemini → Groq → OpenAI-compatível (a primeira chave encontrada é usada; se a chamada falhar, o próximo é tentado).

Isso também significa: se o professor configurou sua própria chave no hub PlayerGames, o PlayerHUD a utiliza automaticamente — sem necessidade de recadastrar no PlayerHUD.

### Provedores diretos suportados

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **APIs compatíveis com OpenAI** — Qualquer provedor que siga o formato da API OpenAI (ex.: OpenRouter, modelos locais via LM Studio, proxy Ollama, etc.)

Esses serviços seguem seus próprios termos de uso e políticas de privacidade.

### Como obter a chave de API

As chaves de API devem ser criadas diretamente no site oficial do provedor:

- Google Gemini: https://ai.google.dev/
- Groq: https://console.groq.com/
- APIs compatíveis com OpenAI: consulte a documentação do provedor específico

Gemini e Groq atualmente oferecem planos gratuitos, porém as políticas de preços podem variar conforme o volume de uso.

O PlayerHUD não fornece chaves de API.

### Onde a chave é configurada

As chaves de API podem ser configuradas por qualquer uma das seguintes origens (em ordem decrescente de prioridade):

1. **Chave pessoal no PlayerHUD** — configurada individualmente por cada professor na aba *Configurações* do painel de gerenciamento.
2. **Chave pessoal na Central de IA** — configurada pelo professor em *local_aihub → Minhas chaves de IA* (se o hub estiver instalado).
3. **Chave de site no PlayerHUD** — configurada pelo admin em *Administração do site → Plugins → Blocos → PlayerHUD*.
4. **Chave de site na Central de IA** — configurada pelo admin nas configurações do *local_aihub* (se o hub estiver instalado).
5. **Moodle `core_ai`** — configurado pelo admin em *Administração do site → IA → Provedores de IA* (nenhuma chave armazenada no PlayerHUD; consultado apenas quando nenhuma das origens acima tiver chave configurada).

### Transmissão de dados

Quando o recurso de IA é utilizado, os prompts informados são enviados ao provedor selecionado para processamento.

O plugin:
- Não armazena prompts nem histórico de conversa (o histórico do chat é apenas da sessão, no navegador)
- Não armazena respostas brutas da IA
- Apenas salva os objetos do jogo criados dentro do Moodle (itens, missões, capítulos)

Nenhuma comunicação externa ocorre sem ativação explícita de um recurso de IA.
