import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: process.env.CRAFT_HOST || "",
      },
    ],
  },
};

export default nextConfig;
