# 🕹️ PlayerGames Ecosystem

PlayerHUD is part of the **PlayerGames** gamification ecosystem. Together, these plugins transform Moodle into an immersive experience:

* **PlayerHUD Filter:** Enables item drops via shortcodes inside course content.

  👉 <https://github.com/jeanlucio/moodle-filter_playerhud>

* **PlayerHUD Availability Restriction:** Restricts access to course activities based on the student's current level or collected items.

  👉 <https://github.com/jeanlucio/moodle-availability_playerhud>

* **PlayerGroup:** Lets students autonomously form their own groups directly from the activity page — no teacher intervention needed.

  👉 <https://github.com/jeanlucio/moodle-mod_playergroup>

* **PlayerWords:** A word-guessing vocabulary activity. Students guess a hidden word letter by letter, with colour-coded feedback, and the words can come from the course Glossary or from optional AI generation. Items can be required to start a round or to reveal a hint, and an item can be granted for each round won.

  👉 <https://github.com/jeanlucio/moodle-mod_playerwords>

* **PlayerCross:** A deduction crossword activity. Solving a term reveals its shared letters in every pending term and in a mystery phrase, turning vocabulary review into active recall. Like PlayerWords, it imports Glossary entries, offers optional AI generation, and can charge or grant PlayerHUD items.

  👉 <https://github.com/jeanlucio/moodle-mod_playercross>

* **PlayerPuzzle:** A turn-based Match-3 RPG activity. Students combine pieces on a board to attack an opponent, answering questions from the activity's own bank to land critical hits, in a single match or a multi-level campaign. With PlayerHUD in the course, students can convert PlayerHUD coins into PuzzleCoin, a retry can cost an item, and a victory can award one. *Submitted to the Moodle Marketplace and awaiting approval.*

  👉 <https://github.com/jeanlucio/moodle-mod_playerpuzzle>

PlayerWords, PlayerCross and PlayerPuzzle work on their own: PlayerHUD is an optional integration for all three, and they validate every item against the course's own PlayerHUD block, so an item from another course is never used. They spend and grant items through PlayerHUD's External Item API, and the block's reports and audit log attribute each grant or consumption to the activity that caused it.
