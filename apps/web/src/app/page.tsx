import { connection } from "next/server";
import AuthWorkspace from "./auth-workspace";

export default async function Home() {
  await connection();

  return <AuthWorkspace />;
}
