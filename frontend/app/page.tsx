import Link from "next/link";
import { gql } from "@/lib/craft";

// Prevent build-time pre-rendering; env vars are only available at runtime.
export const dynamic = "force-dynamic";

interface Entry {
  title: string;
  slug: string;
  dateCreated: string;
}

const QUERY = `
  query NewsIndex {
    entries(section: "news") {
      title
      slug
      dateCreated @formatDateTime(format: "Y-m-d")
    }
  }
`;

export default async function HomePage() {
  const data = await gql<{ entries: Entry[] }>(QUERY);
  const entries = data.entries ?? [];

  return (
    <>
      <h2 style={{ marginTop: 0 }}>News</h2>
      {entries.length === 0 ? (
        <p style={{ color: "#6b7280" }}>No entries yet. Create some in the CraftCMS control panel.</p>
      ) : (
        <ul style={{ listStyle: "none", padding: 0, margin: 0 }}>
          {entries.map((entry) => (
            <li key={entry.slug} style={{ marginBottom: "1rem", paddingBottom: "1rem", borderBottom: "1px solid #f3f4f6" }}>
              <Link href={`/${entry.slug}`} style={{ textDecoration: "none", color: "#1d4ed8", fontWeight: 500 }}>
                {entry.title}
              </Link>
              <span style={{ marginLeft: "0.75rem", color: "#9ca3af", fontSize: "0.875rem" }}>{entry.dateCreated}</span>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
