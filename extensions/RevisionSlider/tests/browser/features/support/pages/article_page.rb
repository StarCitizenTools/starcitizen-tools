class ArticlePage
  include PageObject

  url_template = '<%= params[:article_name] %>' \
    '<%= "?#{params[:query]}" if params[:query] %>'
  page_url url_template
end
