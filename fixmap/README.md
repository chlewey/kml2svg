# fixmap (version of 2023-04-21)

KML topology editor, the later version of `../fixmap.php` (2018). It reads a KML whose
folders hold areas plus drawn editing lines named `XA - Source - Target` and similar
commands, applies them, and writes a fixed KML.

- `fixmap.php`: the editor (1 590 lines; PHP 8 compatible rename of `empty` to `_empty`, adds `array_clone`).
- `index.php`, `getvar.php`: upload form (Bootstrap) and a debugging page.
- `ini/`: one configuration per map (`[file]`, `[config]`, `[special]`, `[alias]`).
  New in this version: `greaterwar`, `teutonia`, `uscr`, `virginia`. `default.ini` is empty.

Runs from this directory with `in/`, `out/` and `log/` subfolders (not included). Input paths in
the `.ini` files are server paths of the original host and must be adjusted.
