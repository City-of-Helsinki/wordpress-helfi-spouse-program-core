<?php
/**
 * Description: Notice box that slides under the header.
 */

add_shortcode('spouse_notice', 'spouse_notice');

function spouse_notice(){
  $posts = spouse_get_latest_posts();

  if(!$posts){
    return;
  }

  spouse_print_notice($posts);
}

function spouse_get_latest_posts(){
  $options = [
    'post_type' => 'post',
    'post_status' => ['publish'],
    'orderby' => 'date',
    'order' => 'DESC',
    'numberposts' => 2,
    'date_query' => [[
      'after' => '1 week ago',
    ]]
  ];
  return get_posts($options);
}

function spouse_print_notice($posts){

  echo '<div role="alert" class="spouse-notice d-none">';
  echo '<div class="row">';
    echo '<div class="col-10">';
    echo '<ul>';
      foreach($posts as $post) {
        ?>
        <a href="<?php echo get_permalink($post->ID); ?>">
          <li class="list-unstyled">
            <?php
            echo __('Read news about: ') . $post->post_title;
            ?>
          </li>
        </a>
        <?php
      }
      echo '<ul>';
    echo '</div>';
      echo '<div class="col-2">';
        echo '<button id="spouse-close-notification" class="spouse-close-notification" aria-label="Close notification">X</button>';
      echo '</div>';
  echo '</div>';
?>
  </div>
  <script>
    (function(){

      var localstorageTag = 'hidenotification';
      var newsVisited = window.localStorage.getItem('news-visited')
      var lastPostDate = '<?php echo $posts[0]->post_date; ?>';

      //window.localStorage.removeItem(localstorageTag);
      var element = document.getElementById('spouse-close-notification');

      var postDate = new Date(lastPostDate)
      var postTime = Math.floor(postDate.getTime() / 1000);

      if(window.localStorage.getItem(localstorageTag) === null || !window.localStorage.getItem(localstorageTag)){
        element.parentElement.parentElement.parentElement.classList.remove('d-none');
      } else {
        var hideTime = window.localStorage.getItem(localstorageTag);
        if(postTime > hideTime){
          element.parentElement.parentElement.parentElement.classList.remove('d-none');
        }
      }

      element.addEventListener('click', function(){
        element.parentElement.parentElement.parentElement.classList.add('d-none');
        window.localStorage.setItem(localstorageTag, postTime);
      });

      if(newsVisited > postTime){
        window.localStorage.setItem(localstorageTag, postTime);
      }

    })();

    // News visited.
    (function(){

      var lastPostDate = '<?php echo $posts[0]->post_date; ?>';
      var lastPostDateUTC = new Date(lastPostDate).toUTCString();
      var lastPostTime = Math.floor(new Date(lastPostDateUTC).getTime() / 1000);
      var newsVisited = localStorage.getItem('news-visited');
      var menuItems = document.getElementsByClassName('new-news');
      var menuItem = menuItems[0];

      // Check if news page has been visited after last post was made
      if(!newsVisited || newsVisited == undefined){
        // menuItem.classList.add('news-visible');
        Array.prototype.forEach.call(menuItems,function(e){
          e.classList.add('news-visible');
        })
      }

      // Add exclamation mark if there are new posts.
      if(lastPostTime > newsVisited){
        Array.prototype.forEach.call(menuItems,function(e){
          e.classList.add('news-visible');
        })
      }
    })()
  </script>
  <?php

}
