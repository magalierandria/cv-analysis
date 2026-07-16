/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./public/Views/**/*.php"],
  // Les classes composants ci-dessous sont définies via @apply dans
  // assets/css/input.css (@layer components). Le scanner de contenu de
  // Tailwind ne les génère que si leur nom apparaît littéralement dans les
  // vues PHP scannées — on les liste ici pour garantir leur présence dans
  // le CSS compilé indépendamment de l'état des vues au moment du build.
  safelist: [
    "btn",
    "btn-default",
    "btn-outline",
    "btn-destructive",
    "btn-ghost",
    "btn-sm",
    "card",
    "card-header",
    "card-title",
    "card-content",
    "badge-default",
    "badge-secondary",
    "badge-destructive",
    "badge-success",
    "badge-warning",
    "badge-info",
    "input",
    "label",
    "table-shadcn",
    "alert-destructive",
    "alert-warning",
    "alert-info",
  ],
  theme: {
    extend: {
      colors: {
        background: "rgb(var(--background) / <alpha-value>)",
        foreground: "rgb(var(--foreground) / <alpha-value>)",
        card: "rgb(var(--card) / <alpha-value>)",
        border: "rgb(var(--border) / <alpha-value>)",
        muted: {
          DEFAULT: "rgb(var(--muted) / <alpha-value>)",
          foreground: "rgb(var(--muted-foreground) / <alpha-value>)",
        },
        primary: {
          DEFAULT: "rgb(var(--primary) / <alpha-value>)",
          foreground: "rgb(var(--primary-foreground) / <alpha-value>)",
        },
        destructive: "rgb(var(--destructive) / <alpha-value>)",
        success: {
          DEFAULT: "rgb(var(--success) / <alpha-value>)",
          foreground: "rgb(var(--success-foreground) / <alpha-value>)",
        },
        warning: {
          DEFAULT: "rgb(var(--warning) / <alpha-value>)",
          foreground: "rgb(var(--warning-foreground) / <alpha-value>)",
        },
        info: {
          DEFAULT: "rgb(var(--info) / <alpha-value>)",
          foreground: "rgb(var(--info-foreground) / <alpha-value>)",
        },
        sidebar: {
          DEFAULT: "rgb(var(--sidebar) / <alpha-value>)",
          foreground: "rgb(var(--sidebar-foreground) / <alpha-value>)",
        },
        "sidebar-border": "rgb(var(--sidebar-border) / <alpha-value>)",
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
      },
      fontFamily: {
        sans: ["Inter", "system-ui", "sans-serif"],
      },
    },
  },
};
