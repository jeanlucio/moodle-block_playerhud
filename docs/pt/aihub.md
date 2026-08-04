# 🧩 Integração Opcional: AI Hub

Os recursos opcionais de IA do PlayerHUD (Gerador de Conteúdo e Assistente Game Master) podem recorrer ao **AI Hub** (`local_aihub`, do mesmo autor, parte dos serviços compartilhados do ecossistema PlayerGames). Quando o AI Hub está instalado, qualquer chave pessoal ou de site que um professor ou administrador já tenha configurado nele fica automaticamente disponível para o PlayerHUD — sem precisar reinserir a chave. As chaves do AI Hub ficam entre as próprias chaves do PlayerHUD e o fallback `core_ai` do Moodle na [Cadeia de Provedores de IA](security.html#cadeia-de-provedores-de-ia). Sem o AI Hub instalado, o PlayerHUD simplesmente pula esses dois níveis e resolve a partir das próprias chaves ou do `core_ai`.

👉 <https://github.com/jeanlucio/moodle-local_aihub>
