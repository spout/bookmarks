<?php
ini_set('display_errors', 1);

$config = [
    'title' => "Bookmarks",
    'bookmarks' => 'bookmarks.json',
    'auth' => [
        'username' => 'spout',
        'password' => '5p0u7n1k',
    ],
    'themes' => [
        'cerulean',
        'cosmo',
        'cyborg',
        'darkly',
        'flatly',
        'journal',
        'litera',
        'lumen',
        'lux',
        'materia',
        'minty',
        'pulse',
        'sandstone',
        'simplex',
        'sketchy',
        'slate',
        'solar',
        'spacelab',
        'superhero',
        'united',
        'yeti',
    ],
    'theme' => 'flatly',
    'perPage' => 50,
];

session_start();

$action = $_GET['action'] ?? null;
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$dirname = ltrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$theme = $_COOKIE['theme'] ?? $config['theme'];
$loggedIn = !empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']) && $_SERVER['PHP_AUTH_USER'] === $config['auth']['username'] && $_SERVER['PHP_AUTH_PW'] === $config['auth']['password'];
$page = $_GET['page'] ?? 1;
$bookmarks = json_decode(file_get_contents($config['bookmarks']), true);
$errors = [];

define('BASE_URL', "{$protocol}://{$_SERVER['HTTP_HOST']}/{$dirname}");

function h($var)
{
    return htmlspecialchars($var);
}

function debug(...$vars)
{
    foreach ($vars as $var) {
        echo '<pre>';
        echo h(print_r($var, true));
        echo '</pre>';
    }
}

function __($message)
{
    return _($message);
}

function formValue($name, $default = null)
{
    return $_POST[$name] ?? $default;
}

function url($query = [])
{
    return BASE_URL . (!empty($query) ? '?' . http_build_query($query) : '');
}

function redirect($url)
{
    header("Location: {$url}");
    exit;
}

function flash($type, $msg) {
    $_SESSION['flash'][$type][] = $msg;
}

function getFlash()
{
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $messages = $_SESSION['flash'];
    $html = '';
    if (!empty($messages)) {
        foreach ($messages as $type => $msgs) {
            foreach ($msgs as $msg) {
                if (!empty($msg)) {
                    $html .= '<div class="alert alert-' . $type . '">' . $msg . '</div>';
                }
            }
        }
    }

    unset($_SESSION['flash']);

    return $html;
}

if (in_array($action, ['login', 'add', 'edit', 'delete'])) {
    if (!$loggedIn) {
        header(sprintf('WWW-Authenticate: Basic realm="%s"', __("My Realm")));
        http_response_code(401);
        die(__("Not authorized"));
    }
}

$tags = [];
foreach ($bookmarks as $k => $bookmark) {
    foreach ((array)$bookmark['tags'] as $tag) {
        $tags[$tag][] = $k;
    }
}

uasort($tags, function ($a, $b) {
    return count($b) <=> count($a);
});

$save = false;

switch ($action) {
    case 'tags':
        if (!empty($_GET['format']) && $_GET['format'] === 'json') {
            $tags = array_keys($tags);
            if (!empty($_GET['term'])) {
                $tags = array_values(array_filter($tags, function ($val) {
                    return strpos($val, $_GET['term']) !== false;
                }));
            }
            header('Content-Type: application/json');
            echo json_encode($tags);
            exit;
        }
        break;

    case 'add':
    case 'edit':
        if (!empty($_POST)) {
            if (empty($_POST['url'])) {
                $errors['url'] = __("URL is required.");
            }

            if (empty($_POST['title'])) {
                $errors['title'] = __("Title is required.");
            }

            if (empty($_POST['tags'])) {
                $errors['tags'] = __("Tags are required.");
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $bookmarks[] = [
                        'url' => $_POST['url'],
                        'title' => $_POST['title'],
                        'tags' => $_POST['tags'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                } else {
                    array_walk($bookmarks, function (&$value, $key) {
                        if ($key === intval($_GET['key'])) {
                            $value['url'] = $_POST['url'];
                            $value['title'] = $_POST['title'];
                            $value['tags'] = $_POST['tags'];
                        }
                    });
                }
                $save = true;
                flash('success', __("Bookmark was saved successfully!"));
            }
        } elseif ($action === 'edit') {
            $_POST = $bookmarks[$_GET['key']];
        }
        break;

    case 'delete':
        $bookmarks = array_filter($bookmarks, function ($key) {
            return $key !== intval($_GET['key']);
        }, ARRAY_FILTER_USE_KEY);
        $save = true;
        flash('success', __("Bookmark was deleted successfully!"));
        break;

    default:
        break;
}

if ($save === true) {
    file_put_contents($config['bookmarks'], json_encode($bookmarks, JSON_PRETTY_PRINT));
    redirect(url());
}

if (!empty($_GET['tag'])) {
    $bookmarks = array_filter($bookmarks, function ($v) {
        return in_array($_GET['tag'], $v['tags']);
    });
}

uasort($bookmarks, function ($a, $b) {
    return $b['created_at'] <=> $a['created_at'];
});

$bookmarksChunked = array_chunk($bookmarks, $config['perPage'], true);
$bookmarks = $bookmarksChunked[$page - 1] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootswatch/4.4.1/<?php echo $theme; ?>/bootstrap.min.css" id="theme">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.12/css/select2.min.css">
    <title><?php echo h($config['title']); ?></title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <div class="container">
        <a class="navbar-brand" href="<?php echo h(url()); ?>"><?php echo h($config['title']); ?></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item <?php echo $action === 'tags' ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo h(url(['action' => 'tags'])); ?>"><?php echo __("Tags"); ?></a>
                </li>
                <?php if($loggedIn): ?>
                    <li class="nav-item <?php echo $action === 'add' ? 'active' : ''; ?>">
                        <a href="<?php echo h(url(['action' => 'add'])); ?>" class="nav-link"><?php echo __("Add bookmark"); ?></a>
                    </li>
                    <li class="nav-item">
                        <a href="#" onclick="logout()" class="nav-link"><?php echo __("Logout"); ?></a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?php echo h(url(['action' => 'login'])); ?>" class="nav-link"><?php echo __("Login"); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text">
                <?php
                $url = url(['action' => 'add']);
                $bookmarklet = "window.open('$url&url='+encodeURIComponent(window.location.href)+'&title='+encodeURIComponent(document.title));";
                ?>
                <?php echo __("Bookmarklet")?>: <a href="javascript:<?php echo $bookmarklet; ?>"><?php echo sprintf("Save on %s", $config['title']); ?></a>
            </span>

            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown"><i class="fa fa-paint-brush"></i> Theme <span class="caret"></span></a>
                    <div class="dropdown-menu">
                        <?php foreach ($config['themes'] as $t): ?>
                        <a href="#" data-theme="<?php echo $t; ?>" class="dropdown-item <?php echo $theme === $t ? 'active' : ''; ?>"><?php echo ucfirst($t); ?></a>
                        <?php endforeach; ?>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <?php echo getFlash(); ?>
    <?php if(in_array($action, ['add', 'edit'])): ?>
        <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
            <div class="card">
                <div class="card-header">
                    <?php echo __("Add bookmark"); ?>
                </div>
                <div class="card-body">
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo h($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <div class="form-group">
                            <label for="url"><?php echo __("URL"); ?></label>
                            <input type="url" class="form-control" name="url" id="url" value="<?php echo h(formValue('url', $_GET['url'] ?? null)); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="title"><?php echo __("Title"); ?></label>
                            <input type="text" class="form-control" name="title" id="title" value="<?php echo h(formValue('title', $_GET['title'] ?? null)); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="tags"><?php echo __("Tags"); ?></label>
                            <select id="tags" multiple="multiple" class="form-control" name="tags[]" required>
                                <?php foreach (formValue('tags', []) as $tag): ?>
                                    <option value="<?php echo h($tag); ?>" selected><?php echo h($tag); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block"><?php echo __("Save"); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php elseif($action === 'tags'): ?>
        <?php if(!empty($tags)): ?>
            <ul class="list-unstyled">
                <?php foreach ($tags as $tag => $bookmarks): ?>
                    <li><a href="<?php echo h(url(['tag' => $tag])) ; ?>"><?php echo h($tag); ?></a> <span class="badge badge-light"><?php echo count($bookmarks); ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif;?>
    <?php else: ?>
        <?php foreach ($bookmarks as $key => $bookmark): ?>
            <div class="card mb-2">
                <div class="card-header p-2">
                    <a href="<?php echo h($bookmark['url']); ?>" target="_blank"><?php echo h($bookmark['title']); ?></a>
                    <?php if($loggedIn): ?>
                        <div class="float-right">
                            <a href="<?php echo h(url(['action' => 'edit', 'key' => $key])); ?>" class="btn btn-secondary"><?php echo __("Edit"); ?></a>
                            <a href="<?php echo h(url(['action' => 'delete', 'key' => $key])); ?>" class="btn btn-danger" data-confirm="<?php echo __("Are you sure?"); ?>"><?php echo __("Delete"); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-2">
                    <p>
                        <a href="<?php echo h($bookmark['url']); ?>" target="_blank"><?php echo h($bookmark['url']); ?></a>
                    </p>
                    <ul class="list-inline mb-0">
                        <?php foreach ($bookmark['tags'] as $tag): ?>
                            <li class="list-inline-item"><a href="<?php echo h(url(['tag' => $tag])); ?>"><span class="badge badge-secondary"><?php echo h($tag); ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer p-2">
                    <?php echo h($bookmark['created_at']); ?>
                </div>
            </div>
        <?php endforeach;?>

        <?php if(count($bookmarksChunked) > 1): ?>
            <ul class="pagination">
                <?php if(isset($bookmarksChunked[$page - 1 - 1])): ?>
                    <li class="page-item">
                        <a href="<?php echo h(url(['page' => $page - 1])); ?>" class="page-link">&laquo; <?php echo __("Prev"); ?></a>
                    </li>
                <?php endif; ?>
                <?php /*foreach ($bookmarksChunked as $k => $chunk): ?>
                    <li class="page-item">
                        <a href="<?php echo h(url(['page' => $k + 1])); ?>" class="page-link"><?php echo $k + 1; ?></a>
                    </li>
                <?php endforeach;*/ ?>
                <?php if(isset($bookmarksChunked[$page + 1])): ?>
                    <li class="page-item">
                        <a href="<?php echo h(url(['page' => $page + 1])); ?>" class="page-link"><?php echo __("Next"); ?> &raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-cookie/2.2.1/js.cookie.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.12/js/select2.full.min.js"></script>
<script>
  $(function () {
    $('[data-theme]').on('click', function (e) {
      e.preventDefault()
      let theme = $(this).data('theme')
      let $dropdown = $(this).closest('.dropdown-menu')
      Cookies.set('theme', theme)
      $('#theme').attr('href', `https://stackpath.bootstrapcdn.com/bootswatch/4.4.1/${theme}/bootstrap.min.css`)
      $dropdown.find('.active').removeClass('active')
      $dropdown.find(`[data-theme="${theme}"]`).addClass('active')
    })

    $('#tags').select2({
      tags: true,
      tokenSeparators: [','],
      ajax: {
        url: "<?php echo url(['action' => 'tags', 'format' => 'json']); ?>",
        processResults: function (data) {
          return {
            results: $.map(data, function (item) {
              return {
                text: item,
                id: item
              }
            })
          }
        },
        cache: true
      },
      minimumInputLength: 1
    })

    $('[data-confirm]').on('click', function () {
      return confirm($(this).data('confirm'))
    })
  })

  // https://stackoverflow.com/a/30308402
  function logout () {
    $.ajax({
      type: 'GET',
      url: '<?php echo url(['action' => 'login']); ?>',
      dataType: 'json',
      async: true,
      username: 'logout',
      password: 'password'
    }).done(function () {
      alert('<?php echo __("Error!"); ?>')
    }).fail(function () {
      window.location = '/'
    })
    return false
  }
</script>
</body>
</html>
