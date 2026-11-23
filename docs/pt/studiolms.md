# 🧩 Integração Opcional: StudioLMS Course Builder

O **StudioLMS Course Builder** (`local_studiolms`, do mesmo autor, parte da família StudioLMS e não da PlayerGames) pode configurar o PlayerHUD para você enquanto monta um curso. O assistente oferece dois modos, **Padrão** e **Gamificado**; o modo Gamificado só aparece quando o `block_playerhud` está instalado e o professor pode adicionar blocos ao curso. Nesse modo, o StudioLMS adiciona um bloco PlayerHUD ao curso gerado e o configura reaproveitando os próprios geradores do PlayerHUD: uma PlayerCoin solta no fórum de avisos, um pacote de avatares, trocas de moedas por avatares e missões, além de um capítulo narrativo escrito por IA e drops de itens temáticos. Três perfis (Conquista, Narrativa e Social) ajustam XP, missões, ranking e drops. Tudo o que é criado é conteúdo comum do PlayerHUD, editável depois no painel do Mestre do Jogo.

A integração é flexível dos dois lados: o StudioLMS verifica se o PlayerHUD está instalado antes de oferecer o modo Gamificado, e uma falha em qualquer etapa de gamificação é registrada como aviso em vez de interromper a geração do curso. Sem o StudioLMS instalado, o PlayerHUD não é afetado.

👉 <https://github.com/jeanlucio/moodle-local_studiolms>
