import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

const devServerHost = process.env.VITE_DEV_SERVER_HOST || "0.0.0.0";
const devServerPort = Number(process.env.VITE_DEV_SERVER_PORT || 5173);
const hmrHost = process.env.VITE_HMR_HOST || "127.0.0.1";
const hmrPort = Number(process.env.VITE_HMR_PORT || 5173);
const usePolling = process.env.VITE_USE_POLLING !== "false";
const devCorsOrigins = [
  process.env.APP_URL,
  /^http:\/\/127\.0\.0\.1:\d+$/,
  /^http:\/\/localhost:\d+$/,
].filter(Boolean);

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.js"],
      refresh: true,
    }),
  ],
  server: {
    host: devServerHost,
    port: devServerPort,
    strictPort: true,
    origin: `http://${hmrHost}:${hmrPort}`,
    cors: {
      origin: devCorsOrigins,
    },
    hmr: {
      host: hmrHost,
      port: hmrPort,
    },
    watch: {
      usePolling,
    },
  },
});
