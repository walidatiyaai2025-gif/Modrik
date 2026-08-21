import { connection } from "next/server";
import AuthWorkspace from "./auth-workspace";

export function HomeContent() {
  return <AuthWorkspace />;
}

export default async function Home() {
  await connection();

  return <HomeContent />;
}
