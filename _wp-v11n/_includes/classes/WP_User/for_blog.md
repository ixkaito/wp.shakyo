{%- assign class = "WP_User" -%}
{%- assign method = "for_blog" -%}
{%- assign self = class -%}

{%- if include.self -%}
  {%- capture self -%}{{ include.self }}{%- endcapture -%}
{%- endif -%}

<p><code><a href="https://developer.wordpress.org/reference/classes/{{ class | downcase }}/{{ method | downcase }}/">{{ class }}::{{ method }}()</a></code></p>

<blockquote>

{% include classes/wpdb/get_blog_prefix.md %}

{% include classes/{{ self }}/_init_caps.md %}

</blockquote>
