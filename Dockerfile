FROM php:5.6-alpine
RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY . /usr/src/app

RUN apk update \
&& apk add --no-cache unzip wget ca-certificates

ENV HOST 0.0.0.0

CMD php exec.php