# 🕹️ Ecossistema PlayerGames

O PlayerHUD faz parte do ecossistema de gamificação **PlayerGames**. Juntos, esses plugins transformam o Moodle em uma experiência imersiva:

* **Filtro PlayerHUD:** Permite inserir drops de itens por meio de shortcodes no conteúdo do curso.

  👉 <https://github.com/jeanlucio/moodle-filter_playerhud>

* **Restrição de Acesso PlayerHUD:** Restringe o acesso a atividades com base no nível atual do estudante ou nos itens coletados.

  👉 <https://github.com/jeanlucio/moodle-availability_playerhud>

* **PlayerGroup:** Permite que os estudantes formem seus próprios grupos de forma autônoma diretamente na página da atividade — sem necessidade de intervenção do professor.

  👉 <https://github.com/jeanlucio/moodle-mod_playergroup>

* **PlayerWords:** Atividade de vocabulário de adivinhação de palavras. O estudante descobre uma palavra oculta letra por letra, com retorno colorido a cada palpite, e as palavras podem vir do Glossário do curso ou da geração opcional por IA. Itens podem ser exigidos para iniciar uma rodada ou revelar uma dica, e um item pode ser concedido a cada rodada vencida.

  👉 <https://github.com/jeanlucio/moodle-mod_playerwords>

* **PlayerCross:** Atividade de palavras cruzadas por dedução. Resolver um termo revela suas letras compartilhadas em todos os termos pendentes e em uma frase-mistério, transformando a revisão de vocabulário em recuperação ativa. Como o PlayerWords, importa entradas do Glossário, oferece geração opcional por IA e pode cobrar ou conceder itens do PlayerHUD.

  👉 <https://github.com/jeanlucio/moodle-mod_playercross>

* **PlayerPuzzle:** Atividade de RPG Match-3 por turnos. O estudante combina peças em um tabuleiro para atacar um oponente, respondendo a questões do banco da própria atividade para acertar golpes críticos, em uma partida única ou em uma campanha de vários níveis. Com o PlayerHUD no curso, o estudante pode converter moedas do PlayerHUD em PuzzleCoin, uma nova tentativa pode custar um item e uma vitória pode concedê-lo. *Submetido ao Moodle Marketplace, aguardando aprovação.*

  👉 <https://github.com/jeanlucio/moodle-mod_playerpuzzle>

PlayerWords, PlayerCross e PlayerPuzzle funcionam por conta própria: o PlayerHUD é uma integração opcional nos três, e cada item é validado contra o bloco PlayerHUD do próprio curso, de modo que um item de outro curso nunca é usado. Eles gastam e concedem itens pela API Externa de Itens do PlayerHUD, e os relatórios e o log de auditoria do bloco atribuem cada concessão ou consumo à atividade que o causou.
