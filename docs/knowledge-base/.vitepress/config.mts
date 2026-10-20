import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

const guide = [
  { text: 'Using the shop', link: '/guide/' },
  { text: 'Shop setup and staff', link: '/guide/shop-and-staff' },
  { text: 'Customers, vehicles, and repair orders', link: '/guide/customers-vehicles-repair-orders' },
  { text: 'Estimates, inspections, and documents', link: '/guide/estimates-inspections-documents' },
  { text: 'Money, scheduling, and reports', link: '/guide/money-scheduling-reports' },
  { text: 'Communications and connected services', link: '/guide/communications-and-integrations' },
]

const extending = [
  { text: 'Extending ARK Core', link: '/extending/' },
  { text: 'Extension mechanisms', link: '/extending/mechanisms' },
  { text: 'Composer packages', link: '/extending/packages' },
  { text: 'What a plugin system would still need', link: '/extending/gaps' },
]

const integrations = [
  { text: 'Building integrations', link: '/integrations/' },
  { text: 'Authentication', link: '/integrations/authentication' },
  { text: 'HTTP API surfaces', link: '/integrations/http-api' },
  { text: 'Files, evidence, and limits', link: '/integrations/files' },
  { text: 'Capability matrix', link: '/integrations/capability-matrix' },
]

export default withMermaid(defineConfig({
  title: 'ARK Core',
  description: 'Install, operate, extend, and integrate with ARK Core, the open-source shop management system.',
  lang: 'en-US',
  cleanUrls: true,
  lastUpdated: true,
  ignoreDeadLinks: false,
  srcExclude: ['README.md', 'scripts/**'],
  head: [
    ['meta', { name: 'theme-color', content: '#0A1A2F' }],
    ['meta', { name: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'ARK Core Knowledge Base' }],
    ['meta', { property: 'og:description', content: 'Developer and operator documentation for open-source ARK Core.' }],
  ],
  themeConfig: {
    siteTitle: 'ARK Core',
    logo: false,
    outline: { level: [2, 3], label: 'On this page' },
    search: { provider: 'local' },
    darkModeSwitchLabel: 'Appearance',
    sidebarMenuLabel: 'Menu',
    returnToTopLabel: 'Return to top',
    docFooter: { prev: 'Previous', next: 'Next' },
    nav: [
      { text: 'Get started', link: '/getting-started/' },
      { text: 'Extend', link: '/extending/' },
      { text: 'Integrate', link: '/integrations/' },
      { text: 'Mobile', link: '/mobile/' },
      { text: 'Reference', link: '/reference/environment' },
    ],
    sidebar: {
      '/getting-started/': [
        {
          text: 'Getting started',
          items: [
            { text: 'Overview', link: '/getting-started/' },
            { text: 'Requirements', link: '/getting-started/requirements' },
            { text: 'Install with Docker Compose', link: '/getting-started/install-docker' },
            { text: 'Install with PHP', link: '/getting-started/install-php' },
            { text: 'First-run setup', link: '/getting-started/first-run' },
            { text: 'Environment and services', link: '/getting-started/configuration' },
            { text: 'Local development', link: '/getting-started/local-development' },
            { text: 'Production', link: '/getting-started/production' },
            { text: 'Updates, backup, and restore', link: '/getting-started/updates-and-backup' },
            { text: 'Troubleshooting', link: '/getting-started/troubleshooting' },
          ],
        },
      ],
      '/guide/': [{ text: 'Shop guide', items: guide }],
      '/architecture/': [
        {
          text: 'Architecture',
          items: [
            { text: 'How ARK Core is built', link: '/architecture/' },
            { text: 'Lifecycles', link: '/architecture/lifecycles' },
            { text: 'Security boundaries', link: '/architecture/security' },
          ],
        },
      ],
      '/extending/': [{ text: 'Extending', items: extending }],
      '/integrations/': [{ text: 'Integrations', items: integrations }],
      '/mobile/': [
        {
          text: 'Mobile',
          items: [
            { text: 'Building a mobile client', link: '/mobile/' },
            { text: 'What a mobile client can do today', link: '/mobile/workflows' },
          ],
        },
      ],
      '/tutorials/': [
        {
          text: 'Tutorials',
          items: [
            { text: 'Working examples', link: '/tutorials/' },
            { text: 'Listen for a domain event', link: '/tutorials/listen-for-a-domain-event' },
            { text: 'Add an Artisan command', link: '/tutorials/add-an-artisan-command' },
            { text: 'A Composer package', link: '/tutorials/composer-package' },
            { text: 'A read-only client', link: '/tutorials/read-only-client' },
            { text: 'Proposed, not implemented', link: '/tutorials/proposed' },
          ],
        },
      ],
      '/contributing/': [
        {
          text: 'Contributing',
          items: [
            { text: 'Contribute', link: '/contributing/' },
            { text: 'License', link: '/contributing/license' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Environment variables', link: '/reference/environment' },
            { text: 'HTTP surfaces', link: '/reference/http-surfaces' },
            { text: 'Permissions and roles', link: '/reference/permissions' },
            { text: 'Commands, jobs, and events', link: '/reference/commands-jobs-events' },
            { text: 'Storage and domain modules', link: '/reference/storage-and-modules' },
          ],
        },
      ],
    },
    footer: {
      message: 'AGPL-3.0-only. Not legal advice.',
      copyright: 'Copyright (C) 2026 Edward Soares Jr.',
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/EdwardSoaresJr/ark-core' },
    ],
  },
  markdown: {
    theme: { light: 'github-light', dark: 'github-dark' },
  },
}))
