<?
/* foreach ($results as &$article) { */
/*     unset($article->generated_html); */
/* } */
$pagination['has_next'] =  $this->Paginator->hasNext();
$pagination['has_prev'] =  $this->Paginator->hasPrev();
$pagination['current_page']  =  (int)$this->Paginator->current();

echo json_encode(compact("results", "pagination"), JSON_PRETTY_PRINT);

