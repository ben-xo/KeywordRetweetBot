FROM ubuntu:xenial
RUN apt-get update -y && apt-get install -y php7.0-cli php7.0-curl
RUN mkdir /bot
ADD *.php /bot/
WORKDIR /bot

# retweet.php <app config file> <session file> <watermark file> <user from> <keyword> [<keyword> ...] [--dry-run]

ENTRYPOINT ["php", "retweet.php"]
CMD [""]
