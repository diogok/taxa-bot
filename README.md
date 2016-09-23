# DwC-A Checklist indexer

Index one or several taxonomic checklist from a DarwinCore-Archive into SQLite and ElasticSearch.

## Usage

    $ php html/dwca2sql.php

You can also set the environment variables to index into elasticsearch, as such:

    $ ELASTICSEARCH=http://localhost:9200 INDEX=dwc php html/dwca2sql.php

It will read the taxa-bot.list either at /etc/biodiv/taxa-bot.list or at html/taxa-bot.list

The SQLite file will be created at html/data/taxa.db

## License

MIT

