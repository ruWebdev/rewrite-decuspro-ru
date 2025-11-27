<?php
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');
jimport('joomla.application.component.helper');

class PlgSystemApicontent extends JPlugin
{
    public function onAfterRoute()
    {
        $app = JFactory::getApplication();

        // Только сайт, не админка
        if ($app->isAdmin()) {
            return;
        }

        // Проверка URL, например: site.ru?option=com_api&task=addcontent
        $input = $app->input;
        $option = $input->getCmd('option');
        $task   = $input->getCmd('task');

        if ($option !== 'com_api') {
            return;
        }

        // Проверка API-ключа, если он задан в настройках плагина
        if (!$this->checkApiKey()) {
            return;
        }

        switch ($task) {
            case 'addcontent':
                $this->handleAddContent();
                break;
            case 'getcategories':
                $this->handleGetCategories();
                break;
            case 'getusers':
                $this->handleGetUsers();
                break;
            case 'articles':
                $this->handleGetArticles();
                break;
            case 'article':
                $this->handleGetArticle();
                break;
            case 'article_update':
                $this->handleUpdateArticle();
                break;
            case 'article_mark_processed':
                $this->handleMarkProcessed();
                break;
            case 'articles_count':
                $this->handleGetArticlesCount();
                break;
            default:
                return;
        }
    }

    private function sendResponse($data)
    {
        $this->setCorsHeaders();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        JFactory::getApplication()->close();
    }

    private function sendNoContentResponse()
    {
        $this->setCorsHeaders();
        header('HTTP/1.1 204 No Content');
        header('Content-Length: 0');
        JFactory::getApplication()->close();
    }

    private function setCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-Key');
        header('Access-Control-Max-Age: 86400');
    }

    private function checkApiKey()
    {
        $configuredKey = trim((string) $this->params->get('api_key', ''));
        if ($configuredKey === '') {
            // Ключ не задан – проверку не выполняем
            return true;
        }

        $headerKey = '';
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $headerKey = trim((string) $_SERVER['HTTP_X_API_KEY']);
        }

        $app = JFactory::getApplication();
        $input = $app->input;
        $queryKey = trim((string) $input->getString('api_key', ''));

        $provided = $headerKey !== '' ? $headerKey : $queryKey;

        $valid = false;
        if ($provided !== '') {
            if (function_exists('hash_equals')) {
                $valid = hash_equals($configuredKey, $provided);
            } else {
                $valid = ($configuredKey === $provided);
            }
        }

        if (!$valid) {
            $this->setCorsHeaders();
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
            JFactory::getApplication()->close();
            return false;
        }

        return true;
    }

    private function handleAddContent()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only POST allowed']);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $this->sendResponse(['status' => 'error', 'message' => 'Invalid JSON']);
        }

        $catid = $this->resolveCategoryId($data);
        if (!$catid) {
            $this->sendResponse(['status' => 'error', 'message' => 'Category not found']);
        }

        $title     = $data['title'] ?? 'Без названия';
        $introtext = $data['introtext'] ?? '';
        $fulltext  = $data['fulltext'] ?? '';
        $alias     = JFilterOutput::stringURLSafe($title);

        $authorId = $this->resolveAuthorId($data);
        if ($authorId === null) {
            $authorId = JFactory::getUser()->id ?: 0;
        }

        $article = JTable::getInstance('content');
        $article->title       = $title;
        $article->alias       = $alias;
        $article->introtext   = $introtext;
        $article->fulltext    = $fulltext;
        $article->catid       = $catid;
        $article->state       = 1;
        $article->created     = JFactory::getDate()->toSql();
        $article->created_by  = (int) $authorId;
        if (!empty($data['created_by_alias'])) {
            $article->created_by_alias = $data['created_by_alias'];
        }
        $article->language    = '*';

        try {
            if (!$article->store()) {
                $this->sendResponse(['status' => 'error', 'message' => $article->getError()]);
            }
            $this->sendResponse(['status' => 'ok', 'id' => $article->id]);
        } catch (Exception $e) {
            $this->sendResponse(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function handleGetCategories()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only GET allowed']);
        }

        $db = JFactory::getDbo();
        // Primary query: all published content categories ordered by tree
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'alias', 'path', 'parent_id', 'level']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('parent_id') . ' != 0')
            ->order($db->quoteName('lft') . ' ASC');

        $db->setQuery($query);
        $categories = (array) $db->loadAssocList();

        // Fallback: if only one category (e.g., "Uncategorized") is returned, broaden the selection
        if (count($categories) <= 1) {
            $fallback = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias', 'path', 'parent_id', 'level']))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('parent_id') . ' != 0')
                ->order($db->quoteName('lft') . ' ASC');
            $db->setQuery($fallback);
            $fallbackCategories = (array) $db->loadAssocList();
            if (!empty($fallbackCategories)) {
                $categories = $fallbackCategories;
            }
        }

        $this->sendResponse(['status' => 'ok', 'categories' => $categories]);
    }

    private function handleGetUsers()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only GET allowed']);
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name', 'username']))
            ->from($db->quoteName('#__users'))
            ->order($db->quoteName('name') . ' ASC');

        $db->setQuery($query);
        $users = (array) $db->loadAssocList();

        $this->sendResponse(['status' => 'ok', 'users' => $users]);
    }

    private function handleGetArticles()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only GET allowed']);
        }

        $app   = JFactory::getApplication();
        $input = $app->input;

        $categoryId      = (int) $input->getInt('category', 0);
        $authorId        = (int) $input->getInt('author', 0);
        $limit           = (int) $input->getInt('limit', 0);
        $offset          = (int) $input->getInt('offset', 0);
        $onlyUnprocessed = (int) $input->getInt('onlyUnprocessed', 1) === 1;

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'catid', 'created_by', 'metakey', 'alias', 'title']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');

        if ($categoryId > 0) {
            $query->where($db->quoteName('catid') . ' = ' . (int) $categoryId);
        }

        if ($authorId > 0) {
            $query->where($db->quoteName('created_by') . ' = ' . (int) $authorId);
        }

        // Фильтруем уже переписанные статьи по маркеру oldrewrite_processed до применения LIMIT
        $marker = 'oldrewrite_processed';

        if ($onlyUnprocessed) {
            $markerCondition = '('
                . $db->quoteName('metakey') . ' IS NULL'
                . ' OR ' . $db->quoteName('metakey') . " = ''"
                . ' OR ' . $db->quoteName('metakey') . ' NOT LIKE ' . $db->quote('%' . $marker . '%')
                . ')';

            $query->where($markerCondition);
        }

        $query->order($db->quoteName('id') . ' DESC');

        if ($limit > 0) {
            $db->setQuery($query, $offset, $limit);
        } else {
            $db->setQuery($query, $offset);
        }

        $rows = (array) $db->loadAssocList();

        $articles = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $articles[] = [
                'id'       => $id,
                'title'    => $row['title'],
                'category' => (int) $row['catid'],
                'author'   => (int) $row['created_by'],
                'url'      => JRoute::_('index.php?option=com_content&view=article&id=' . $id, false)
            ];
        }

        $this->sendResponse(['status' => 'ok', 'articles' => $articles]);
    }

    /**
     * Возвращает общее количество статей (необработанных или всех) по фильтрам
     */
    private function handleGetArticlesCount()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only GET allowed']);
        }

        $app   = JFactory::getApplication();
        $input = $app->input;

        $categoryId      = (int) $input->getInt('category', 0);
        $authorId        = (int) $input->getInt('author', 0);
        $onlyUnprocessed = (int) $input->getInt('onlyUnprocessed', 1) === 1;

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');

        if ($categoryId > 0) {
            $query->where($db->quoteName('catid') . ' = ' . (int) $categoryId);
        }

        if ($authorId > 0) {
            $query->where($db->quoteName('created_by') . ' = ' . (int) $authorId);
        }

        $marker = 'oldrewrite_processed';

        if ($onlyUnprocessed) {
            $markerCondition = '('
                . $db->quoteName('metakey') . ' IS NULL'
                . ' OR ' . $db->quoteName('metakey') . " = ''"
                . ' OR ' . $db->quoteName('metakey') . ' NOT LIKE ' . $db->quote('%' . $marker . '%')
                . ')';

            $query->where($markerCondition);
        }

        $db->setQuery($query);
        $count = (int) $db->loadResult();

        $this->sendResponse(['status' => 'ok', 'count' => $count]);
    }

    private function handleGetArticle()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only GET allowed']);
        }

        $app   = JFactory::getApplication();
        $input = $app->input;
        $id    = (int) $input->getInt('id', 0);

        if ($id <= 0) {
            $this->sendResponse(['status' => 'error', 'message' => 'Invalid article id']);
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $id)
            ->setLimit(1);

        $db->setQuery($query);
        $row = $db->loadAssoc();

        if (!$row) {
            $this->sendResponse(['status' => 'error', 'message' => 'Article not found']);
        }

        $this->sendResponse([
            'status'    => 'ok',
            'id'        => (int) $row['id'],
            'title'     => $row['title'],
            'introtext' => $row['introtext'],
            'fulltext'  => $row['fulltext'],
            'metadata'  => [
                'catid'      => (int) $row['catid'],
                'created_by' => (int) $row['created_by']
            ]
        ]);
    }

    private function handleUpdateArticle()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only POST allowed']);
        }

        $app   = JFactory::getApplication();
        $input = $app->input;
        $id    = (int) $input->getInt('id', 0);

        if ($id <= 0) {
            $this->sendResponse(['status' => 'error', 'message' => 'Invalid article id']);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $this->sendResponse(['status' => 'error', 'message' => 'Invalid JSON']);
        }

        $title = isset($data['title']) ? trim((string) $data['title']) : '';

        // Основной текст статьи: сначала пытаемся взять fulltext,
        // если его нет — используем introtext как источник для полного текста
        $fulltext = '';
        if (isset($data['fulltext'])) {
            $fulltext = (string) $data['fulltext'];
        } elseif (isset($data['introtext'])) {
            $fulltext = (string) $data['introtext'];
        }

        $article = JTable::getInstance('content');
        if (!$article->load($id)) {
            $this->sendResponse(['status' => 'error', 'message' => $article->getError() ?: 'Article not found']);
        }

        if ($title !== '') {
            $article->title = $title;
            $article->alias = JFilterOutput::stringURLSafe($title);
        }

        // Записываем результат рерайта в introtext
        $article->introtext = $fulltext;
        $article->modified  = JFactory::getDate()->toSql();

        try {
            if (!$article->store()) {
                $this->sendResponse(['status' => 'error', 'message' => $article->getError()]);
            }
            $this->sendResponse(['status' => 'ok', 'id' => (int) $article->id]);
        } catch (Exception $e) {
            $this->sendResponse(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function handleMarkProcessed()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendNoContentResponse();
        }

        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(['status' => 'error', 'message' => 'Only POST allowed']);
        }

        $app   = JFactory::getApplication();
        $input = $app->input;
        $id    = (int) $input->getInt('id', 0);

        if ($id <= 0) {
            $this->sendResponse(['status' => 'error', 'message' => 'Invalid article id']);
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('metakey'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $id)
            ->setLimit(1);

        $db->setQuery($query);
        $current = (string) $db->loadResult();

        $marker = 'oldrewrite_processed';

        if (stripos($current, $marker) === false) {
            $keys = trim($current);
            if ($keys === '') {
                $keys = $marker;
            } else {
                $keys .= ', ' . $marker;
            }

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__content'))
                ->set($db->quoteName('metakey') . ' = ' . $db->quote($keys))
                ->where($db->quoteName('id') . ' = ' . (int) $id);

            $db->setQuery($update);
            $db->execute();
        }

        $this->sendResponse(['status' => 'ok']);
    }

    private function resolveCategoryId(array $data)
    {
        if (isset($data['catid'])) {
            return (int) $data['catid'];
        }

        if (isset($data['category_id'])) {
            return (int) $data['category_id'];
        }

        if (!empty($data['category_alias'])) {
            return $this->findCategoryIdBy('alias', $data['category_alias']);
        }

        if (!empty($data['category_title'])) {
            return $this->findCategoryIdBy('title', $data['category_title']);
        }

        return null;
    }

    private function findCategoryIdBy($field, $value)
    {
        $allowedFields = ['alias', 'title'];
        if (!in_array($field, $allowedFields, true)) {
            return null;
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName($field) . ' = ' . $db->quote($value))
            ->order($db->quoteName('lft') . ' ASC');

        $db->setQuery($query);

        $result = $db->loadResult();

        return $result ? (int) $result : null;
    }

    private function resolveAuthorId(array $data)
    {
        if (isset($data['author_id'])) {
            return (int) $data['author_id'];
        }

        if (!empty($data['author_username'])) {
            return $this->findUserIdBy('username', $data['author_username']);
        }

        if (!empty($data['author_name'])) {
            return $this->findUserIdBy('name', $data['author_name']);
        }

        return null;
    }

    private function findUserIdBy($field, $value)
    {
        $allowedFields = ['username', 'name'];
        if (!in_array($field, $allowedFields, true)) {
            return null;
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName($field) . ' = ' . $db->quote($value))
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query);
        $result = $db->loadResult();
        return $result ? (int) $result : null;
    }
}
