FROM golang:1.25 AS builder
WORKDIR /app
COPY main.go go.mod index.html ./
RUN CGO_ENABLED=0 go build -o server .

FROM scratch
COPY --from=builder /app/server /server
EXPOSE 8080
CMD ["/server"]