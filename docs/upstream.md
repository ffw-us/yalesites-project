# Upstream architecture

This project follows the authoritative [drupal-recommended](https://github.com/pantheon-upstreams/drupal-recommended) repository managed by Pantheon. Updates to Pantheon scaffolding and Drupal Core may be pulled from this remote. Pantheon's [integrated composer](https://pantheon.io/docs/integrated-composer) is employed as part of a one-click continuous integration strategy.

Files and directories important to the structure of the upstream include:

```bash
└─ config/
└─ upstream-configuration/
   └─ composer.json
└─ web/sites/default
   └─ settings.php
├─ .gitignore
├─ composer.json
├─ pantheon.upstream.yml
└─ README.md
```

- `config/`: Not currently in use on this project.
- `upstream-configuration/composer.json`: The two different composer.json files allow customization of individual sites without inherent merge conflicts and enable one-click updates. Platform-wide dependencies, such as the yalesites_profile are included in this file.
- `web/sites/default/settings.php`: Boilerplate for the site-specific settings file.
- `.gitignore`: Prevents build artifacts generated by Composer from being committed to the upstream or site code repositories.
- `composer.json`: The composer file at the project root is for project settings and scaffolding. Vendor-supported sites or sites with emergent features may add dependencies or customizations here. Developers should avoid updating this file in the upstream.
- `pantheon.upstream.yml`: The build_step: true directive in pantheon.upstream.yml enables the build step.