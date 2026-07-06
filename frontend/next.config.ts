import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  images: {
    remotePatterns: process.env.CRAFT_HOST
      ? [{ protocol: "https", hostname: process.env.CRAFT_HOST }]
      : [],
  },
};

export default nextConfig;
