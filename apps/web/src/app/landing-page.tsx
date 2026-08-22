"use client";

import Link from "next/link";
import { useState } from "react";

type Locale = "ar" | "en" | "fr";

type Copy = {
  eyebrow: string;
  title: string;
  body: string;
  studentTitle: string;
  studentBody: string;
  studentCta: string;
  adminTitle: string;
  adminBody: string;
  adminCta: string;
  loginLabel: string;
  demoLabel: string;
  footer: string;
};

const copy: Record<Locale, Copy> = {
  ar: {
    eyebrow: "منصة تعلم ذكية وآمنة",
    title: "مُدرك يساعد الطالب على التعلّم بوضوح، ويمنح الإدارة رؤية وتحكمًا أفضل.",
    body: "اختر بوابة الدخول المناسبة لتجربة نسخة العرض الحالية من مُدرك.",
    studentTitle: "تجربة الطالب",
    studentBody: "الدروس، المذاكرة، التدريب، المحاولات، التقدم وتجربة الاستخدام متعددة اللغات.",
    studentCta: "دخول الطالب",
    adminTitle: "تجربة مسؤول النظام",
    adminBody: "لوحة الإدارة وإدارة المحتوى، المراجعة والنشر والعمليات التشغيلية.",
    adminCta: "دخول مسؤول النظام",
    loginLabel: "تسجيل الدخول",
    demoLabel: "نسخة تجريبية",
    footer: "MODRIK | مُدرك — Demo environment",
  },
  en: {
    eyebrow: "Secure, intelligent learning",
    title: "MODRIK gives learners a focused workspace and administrators clear operational control.",
    body: "Choose the portal you want to explore in the current MODRIK demo environment.",
    studentTitle: "Student demo",
    studentBody: "Lessons, study, practice, attempts, progress and the multilingual learner experience.",
    studentCta: "Student sign in",
    adminTitle: "System admin demo",
    adminBody: "Administration, content operations, review, publication and operational workflows.",
    adminCta: "Admin sign in",
    loginLabel: "Sign in",
    demoLabel: "Demo environment",
    footer: "MODRIK | مُدرك — Demo environment",
  },
  fr: {
    eyebrow: "Apprentissage intelligent et sécurisé",
    title: "MODRIK offre aux élèves un espace clair et aux administrateurs un contrôle opérationnel précis.",
    body: "Choisissez le portail que vous souhaitez explorer dans la démonstration MODRIK.",
    studentTitle: "Démo élève",
    studentBody: "Leçons, étude, exercices, tentatives, progression et expérience multilingue.",
    studentCta: "Connexion élève",
    adminTitle: "Démo administrateur système",
    adminBody: "Administration, contenu, révision, publication et opérations.",
    adminCta: "Connexion administrateur",
    loginLabel: "Se connecter",
    demoLabel: "Environnement de démo",
    footer: "MODRIK | مُدرك — Demo environment",
  },
};

export default function LandingPage({ adminUrl }: { adminUrl: string }) {
  const [locale, setLocale] = useState<Locale>("en");
  const text = copy[locale];
  const direction = locale === "ar" ? "rtl" : "ltr";

  return (
    <main className="landing-shell" lang={locale} dir={direction} data-testid="modrik-landing-page">
      <header className="landing-nav">
        <Link className="landing-brand" href="/" aria-label="MODRIK home">
          <span className="landing-brand-mark" aria-hidden="true">M</span>
          <span><strong>MODRIK</strong><small lang="ar" dir="rtl">مُدرك</small></span>
        </Link>
        <div className="landing-nav-actions">
          <div className="landing-locale" aria-label="Language">
            {(["ar", "en", "fr"] as const).map((item) => (
              <button key={item} type="button" aria-pressed={locale === item} onClick={() => setLocale(item)}>
                {item.toUpperCase()}
              </button>
            ))}
          </div>
          <Link className="landing-login-link" href="/student" data-testid="modrik-student-login-link">{text.loginLabel}</Link>
        </div>
      </header>

      <section className="landing-hero">
        <div className="landing-hero-copy">
          <span className="landing-badge">{text.demoLabel}</span>
          <p className="landing-eyebrow">{text.eyebrow}</p>
          <h1>{text.title}</h1>
          <p className="landing-lead">{text.body}</p>
        </div>

        <div className="landing-portals" aria-label="MODRIK demo portals">
          <article className="landing-portal-card landing-portal-student">
            <span className="landing-portal-icon" aria-hidden="true">01</span>
            <h2>{text.studentTitle}</h2>
            <p>{text.studentBody}</p>
            <Link className="landing-primary-cta" href="/student" data-testid="modrik-student-portal-cta">{text.studentCta}</Link>
          </article>

          <article className="landing-portal-card landing-portal-admin">
            <span className="landing-portal-icon" aria-hidden="true">02</span>
            <h2>{text.adminTitle}</h2>
            <p>{text.adminBody}</p>
            <a className="landing-secondary-cta" href={adminUrl}>{text.adminCta}</a>
          </article>
        </div>
      </section>

      <footer className="landing-footer">{text.footer}</footer>
    </main>
  );
}
