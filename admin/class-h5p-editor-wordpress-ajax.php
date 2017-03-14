<?php

class H5PEditorWordPressAjax implements H5PEditorAjaxInterface {

  /**
   * Gets latest library versions that exists locally
   *
   * @return array Latest version of all local libraries
   */
  public function getLatestLibraryVersions() {
    global $wpdb;

    // Get latest version of local libraries
    $major_versions_sql =
      "SELECT hl.name,
                MAX(hl.major_version) AS major_version
           FROM {$wpdb->prefix}h5p_libraries hl
          WHERE hl.runnable = 1
       GROUP BY hl.name";

    $minor_versions_sql =
      "SELECT hl2.name,
                 hl2.major_version,
                 MAX(hl2.minor_version) AS minor_version
            FROM ({$major_versions_sql}) hl1
            JOIN {$wpdb->prefix}h5p_libraries hl2
              ON hl1.name = hl2.name
             AND hl1.major_version = hl2.major_version
        GROUP BY hl2.name, hl2.major_version";

    return $wpdb->get_results(
      "SELECT hl4.id,
                hl4.name AS machine_name,
                hl4.major_version,
                hl4.minor_version,
                hl4.patch_version,
                hl4.restricted,
                hl4.has_icon
           FROM ({$minor_versions_sql}) hl3
           JOIN {$wpdb->prefix}h5p_libraries hl4
             ON hl3.name = hl4.name
            AND hl3.major_version = hl4.major_version
            AND hl3.minor_version = hl4.minor_version
       GROUP BY hl4.name, hl4.major_version, hl4.minor_version");
  }

  /**
   * Get locally stored Content Type Cache. If machine name is provided
   * it will only get the given content type from the cache
   *
   * @param $machineName
   *
   * @return array|object|null Returns results from querying the database
   */
  public function getContentTypeCache($machineName) {
    global $wpdb;

    // Return info of only the content type with the given machine name
    if ($machineName) {
      return $wpdb->get_row($wpdb->prepare(
        "SELECT id, is_recommended
           FROM {$wpdb->base_prefix}h5p_libraries_hub_cache
          WHERE machine_name = %s",
        $machineName
      ));
    }

    return $wpdb->get_results(
      "SELECT * FROM {$wpdb->base_prefix}h5p_libraries_hub_cache"
    );
  }

  /**
   * Gets recently used libraries for the current author
   *
   * @return array machine names. The first element in the array is the
   * most recently used.
   */
  public function getAuthorsRecentlyUsedLibraries() {
    global $wpdb;
    $recently_used = array();

    $result = $wpdb->get_results($wpdb->prepare(
      "SELECT distinct library_name
         FROM {$wpdb->prefix}h5p_events
      WHERE type='content' AND sub_type = 'new' AND user_id = %d
      ORDER BY created_at DESC",
      get_current_user_id()
    ));

    foreach ($result as $row) {
      $recently_used[] = $row->library_name;
    }

    return $recently_used;
  }

  /**
   * Checks if the provided token is valid for this endpoint
   *
   * @param string $token
   *
   * @return bool True if successful validation
   */
  public function validateEditorToken($token) {
    return wp_verify_nonce($token, 'h5p_editor_ajax');
  }
}
