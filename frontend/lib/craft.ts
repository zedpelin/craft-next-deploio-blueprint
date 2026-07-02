export async function gql<T = Record<string, unknown>>(
  query: string,
  variables?: Record<string, unknown>,
  revalidate = 60,
): Promise<T> {
  const GQL_URL = process.env.CRAFT_GQL_URL;
  const GQL_TOKEN = process.env.CRAFT_GQL_TOKEN;

  // During next build the env vars are absent — return empty data so the build
  // succeeds; real content is fetched at request time.
  if (!GQL_URL) return {} as T;

  const res = await fetch(GQL_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...(GQL_TOKEN ? { Authorization: `Bearer ${GQL_TOKEN}` } : {}),
    },
    body: JSON.stringify({ query, variables }),
    next: { revalidate },
  });

  if (!res.ok) throw new Error(`GraphQL request failed: ${res.status}`);

  const { data, errors } = await res.json();
  if (errors?.length) throw new Error(errors[0].message);
  return data as T;
}
