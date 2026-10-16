# wordpress.org assets

Files for the `assets/` folder of the plugin's SVN repository (not part of the plugin ZIP):
icons, banners and the screenshots described in `readme.txt` → `== Screenshots ==`.
The screenshots use made-up data (names of famous tennis players).

Plugin ZIP for the submission: `git archive --format=zip --prefix=fair-member-fees/ -o fair-member-fees-1.0.0.zip HEAD`

`blueprints/blueprint.json` is the WordPress Playground blueprint of the Live Preview on wordpress.org
(SVN `assets/blueprints/blueprint.json`). It installs the plugin from wordpress.org and creates a demo club
(members, volunteer hours, two payments, USD) and the pages Membership fees, Members and How the fee is calculated.
The PHP of its `runPHP` step is a JSON string; test it with
`npx @wp-playground/cli run-blueprint --blueprint=.wordpress-org/blueprints/blueprint.json`.
Live Preview has to be turned on in the plugin's Advanced view on wordpress.org.
