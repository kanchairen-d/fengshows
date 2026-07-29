FROM docker.m.daocloud.io/library/golang:1.22-alpine AS builder
WORKDIR /build
COPY main.go go.mod ./
RUN CGO_ENABLED=0 GOOS=linux go build -o fengshows .

FROM scratch
COPY --from=ghcr.io/kanchairen-d/php-box /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/
COPY --from=ghcr.io/kanchairen-d/php-box /usr/share/zoneinfo /usr/share/zoneinfo
COPY --from=ghcr.io/kanchairen-d/php-box /etc/localtime /etc/localtime
COPY --from=builder /build/fengshows /fengshows

EXPOSE 3233
ENV PORT=3233
CMD ["/fengshows"]