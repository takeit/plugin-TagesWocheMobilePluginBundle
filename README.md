TagesWocheMobilePluginBundle
===================

## Installation/Updating/Removing
#### Overview

This is a TagesWoche specific plugin. It adds the mobile API and mobile
subscription services to Newscoop.

#### Installation

```
    php application/console plugins:install "newscoop/tw-mobile-plugin-bundle" --env=prod # installs this plugin
```
Install command will add your package to your composer.json file (and install it) and update plugins/avaiable_plugins.json file (used for plugin booted as Bundle). This command will also fire "plugin.install" event with plugin_name parameter in event data

#### Removing

```
    php application/console plugins:remove "newscoop/tw-mobile-plugin-bundle" --env=prod # removes this plugin
```
Remove command will remove your package from composer.json file and update your dependencies (for now this is only way), it will also remove info about plugin from plugins/avaiable_plugins.json file and fire "plugin.remove" event with plugin_name parameter in event data.

#### Updating

```
    php application/console plugins:update "newscoop/tw-mobile-plugin-bundle" --env=prod # updates this plugin
```

Update command is little specific - it will first remove your your plugin form newscoop (but won't fire plugin.remove event) and after that will install again your plugin (again without plugin.install event). After all of that it will fire plugin.update event.
