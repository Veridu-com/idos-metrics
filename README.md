# idOS Metrics

# Setup

You can read how to setup the idOS Metrics in the [Setup Manual](Setup.md)

# Operation

You can read how to operate the idOS Metrics in the [Operation Manual](Operation.md)

# Documentation

To generate the internal documentation, run:

```bash
./vendor/bin/phploc --log-xml=build/phploc.xml cli/
./vendor/bin/phpmd cli/ xml cleancode,codesize,controversial,design,naming,unusedcode --reportfile build/pmd.xml
./vendor/bin/phpcs --standard=VeriduRuleset.xml --report=xml --report-file=build/phpcs.xml cli/
./vendor/bin/phpdox --file phpdox.xml.dist
```

The files will be stored at [docs/](docs/).
