# Terminus Plugin Search

A Terminus plugin to help search for available plugins

## Installation:

Refer to the [Terminus Wiki](https://github.com/pantheon-systems/terminus/wiki/Plugins).

Windows users should install and run `terminus` in [Git for Windows](https://git-for-windows.github.io/).

## Usage:
Search for plugin(s) *(Partial strings perform a fuzzy search)*:
```
$ terminus plugin search | find plugin-name-1 [plugin-name-2] ...
```
Add searchable plugin Git registries:
```
$ terminus plugin registry | reg add <URL to plugin Git registry 1> [<URL to plugin Git registry 2>] ...
```
List searchable plugin Git registries:
```
$ terminus plugin registry | reg list
```
Remove searchable plugin Git registries:
```
$ terminus plugin registry | reg remove <URL to plugin Git registry 1> [<URL to plugin Git registry 2>] ...
```
Restore the list of well-known searchable plugin Git registries *(also removes any custom registries)*:
```
$ terminus plugin registry | reg restore
```

## Examples:
Search for all plugins with the word `awesome` in the plugin name or title:
```
$ terminus plugin search awesome
```
Search for all plugins with the word `awesome` or `sauce` in the plugin name or title:
```
$ terminus plugin find awesome sauce
```
Search for all plugins in searchable plugin Git registries:
```
$ terminus plugin search plugin
```
Add a searchable plugin Git registry:
```
$ terminus plugin registry add https://github.com/path/to/plugin/registry
```
List searchable plugin Git registries:
```
$ terminus plugin registry list
```
Remove a searchable plugin Git registry:
```
$ terminus plugin registry remove https://github.com/path/to/plugin/registry
```
Restore the list of well-known searchable plugin Git registries:
```
$ terminus plugin registry restore
```

## Manage:
To add, list, update and remove plugins, see the companion plugin [Terminus Plugin Manager](https://github.com/uberhacker/tpm).

## Update:
If you update this plugin, remember to execute the following:
```
$ terminus plugin registry restore
```
This will increase the searchability of plugins.  Note that this will also remove any custom registries that have been added,
so remember to execute `terminus plugin registry list` beforehand so you can add any custom registries back, if necessary.
