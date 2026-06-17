FROM ubuntu:latest
LABEL authors="ABOBUS"

ENTRYPOINT ["top", "-b"]
