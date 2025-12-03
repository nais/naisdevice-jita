package main

import (
	"embed"
	"log"
	"net/http"
)

//go:embed index.html
var content embed.FS

func main() {
	http.HandleFunc("GET /", func(w http.ResponseWriter, _ *http.Request) {
		data, err := content.ReadFile("index.html")
		if err != nil {
			http.Error(w, "file not found", http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "text/html; charset=utf-8")
		_, _ = w.Write(data)
	})

	log.Println("Listening on :8080")
	log.Fatal(http.ListenAndServe(":8080", nil))
}
