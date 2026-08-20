const surfaces = ["Study", "Practice", "Progress"];

export default function Home() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-background px-6 py-16 text-ink">
      <section className="w-full max-w-5xl overflow-hidden rounded-[var(--modrik-radius-lg)] border border-blue/10 bg-white shadow-2xl shadow-blue/10">
        <div className="grid gap-10 px-7 py-10 md:grid-cols-[1.2fr_0.8fr] md:px-12 md:py-14">
          <div>
            <p className="mb-5 text-sm font-semibold uppercase tracking-[0.2em] text-blue">
              Student Web · Bootstrap shell
            </p>
            <h1 className="max-w-2xl text-4xl font-bold leading-tight text-navy sm:text-6xl">
              MODRIK <span className="text-teal">| مُدرك</span>
            </h1>
            <p className="mt-5 max-w-xl text-lg leading-8 text-slate">
              The desktop-first learning workspace is being built on the locked
              Brand v1 system. The public Coming Soon page remains the live shell.
            </p>
            <p className="font-arabic mt-4 text-lg leading-9 text-blue" lang="ar" dir="rtl">
              مساحة تعلّم واضحة للمذاكرة والتدريب ومتابعة التقدّم.
            </p>
          </div>

          <aside className="rounded-[var(--modrik-radius-md)] bg-navy p-6 text-white" aria-label="Application status">
            <div className="flex items-center gap-3">
              <span className="h-3 w-3 rounded-full bg-teal" aria-hidden="true" />
              <span className="font-semibold">Bootstrap in progress</span>
            </div>
            <ul className="mt-7 space-y-3">
              {surfaces.map((surface, index) => (
                <li className="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3" key={surface}>
                  <span>{surface}</span>
                  <span className="text-sm text-sky">0{index + 1}</span>
                </li>
              ))}
            </ul>
          </aside>
        </div>
      </section>
    </main>
  );
}
