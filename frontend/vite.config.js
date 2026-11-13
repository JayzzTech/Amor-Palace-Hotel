import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

const backendUrl = process.env.BACKEND_URL || "http://127.0.0.1:8000";

export default defineConfig(({ mode }) => ({
    plugins: [react()],
    base: mode === "production" ? "/react/" : "/",
    build: {
        outDir: process.env.VERCEL ? "dist" : "../public/react",
        emptyOutDir: true,
    },
    server: {
        port: 5173,
        proxy: { "/api": backendUrl },
    },
}));
