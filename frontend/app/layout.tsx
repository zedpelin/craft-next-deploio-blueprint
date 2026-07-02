import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Craft + Next.js on Deploio",
  description: "Next.js frontend powered by CraftCMS GraphQL",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body style={{ fontFamily: "system-ui, sans-serif", maxWidth: 800, margin: "0 auto", padding: "2rem 1rem" }}>
        <header style={{ marginBottom: "2rem", borderBottom: "1px solid #e5e7eb", paddingBottom: "1rem" }}>
          <a href="/" style={{ textDecoration: "none", color: "inherit" }}>
            <h1 style={{ margin: 0, fontSize: "1.25rem" }}>News</h1>
          </a>
        </header>
        <main>{children}</main>
      </body>
    </html>
  );
}
