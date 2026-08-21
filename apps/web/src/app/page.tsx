import LandingPage from "./landing-page";

export default function Home() {
  const adminUrl = process.env.MODRIK_ADMIN_PORTAL_URL ?? "https://api.demo.modrik.org/admin/login";

  return <LandingPage adminUrl={adminUrl} />;
}
