import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  pageKeyForSlug,
  parsePublicLocale,
  publicPageKeys,
  publicPages,
} from "@/public-site/content";
import { publicMetadata } from "@/public-site/metadata";
import { PublicSite } from "@/public-site/public-site";

export const dynamicParams = false;

export function generateStaticParams() {
  return publicPageKeys.map((key) => ({ publicSlug: publicPages[key].slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ publicSlug: string }>;
}): Promise<Metadata> {
  const { publicSlug } = await params;
  const pageKey = pageKeyForSlug(publicSlug);
  if (!pageKey) return {};
  return publicMetadata(pageKey);
}

export default async function PublicReleasePage({
  params,
  searchParams,
}: {
  params: Promise<{ publicSlug: string }>;
  searchParams: Promise<{ lang?: string | string[] }>;
}) {
  const [{ publicSlug }, query] = await Promise.all([params, searchParams]);
  const pageKey = pageKeyForSlug(publicSlug);
  if (!pageKey) notFound();

  return <PublicSite pageKey={pageKey} locale={parsePublicLocale(query.lang)} />;
}
