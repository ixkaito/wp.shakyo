module.exports = {
  plugins: [
    '@/plugins/global.js'
  ],
  router: {
    extendRoutes (routes, resolve) {
      routes.push({
        path: '/',
        component: '@/pages/index.php.vue',
        name: 'index'
      })
    }
  },
  // generate: {
  //   routes: [
  //     '/'
  //   ]
  // },
  /*
  ** Headers of the page
  */
  head: {
    title: 'WP Shakyo',
    meta: [
      { charset: 'utf-8' },
      { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      { hid: 'description', name: 'description', content: 'Nuxt.js project' }
    ],
    link: [
      { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
      { rel: 'stylesheet', href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css'}
    ]
  },
  /*
  ** Customize the progress bar color
  */
  loading: { color: '#3B8070' },
  /*
  ** Build configuration
  */
  build: {
    /*
    ** Run ESLint on save
    */
    extend (config, { isDev, isClient }) {
      if (isDev && isClient) {
        config.module.rules.push({
          enforce: 'pre',
          test: /\.(js|vue)$/,
          loader: 'eslint-loader',
          exclude: /(node_modules)/
        })
      }
    }
  }
}

