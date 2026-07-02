import { notFound } from "next/navigation";
import { gql } from "@/lib/craft";

export const dynamic = "force-dynamic";

interface EntryDetail {
  title: string;
  slug: string;
  dateCreated: string;
  body?: string;
}

const DETAIL_QUERY = `
  query NewsEntry($slug: [String]) {
    entry(section: "news", slug: $slug) {
      title
      slug
      dateCreated @formatDateTime(format: "Y-m-d")
      ... on news_Entry {
        body
      }
    }
  }
`;

export default async function EntryPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const data = await gql<{ entry: EntryDetail | null }>(DETAIL_QUERY, { slug: [slug] });
  const entry = data.entry;

  if (!entry) notFound();

  return (
    <>
      <p style={{ color: "#9ca3af", fontSize: "0.875rem", marginTop: 0 }}>{entry.dateCreated}</p>
      <h2 style={{ marginTop: "0.25rem" }}>{entry.title}</h2>
      {entry.body && (
        <div dangerouslySetInnerHTML={{ __html: entry.body }} />
      )}
    </>
  );
}
