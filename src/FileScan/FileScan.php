<?php
namespace UrlImageImporter\FileScan;

class FileScan extends BigFileUploadsFileScan {
	public function get_paths_left() {
		return $this->paths_left;
	}
	public function is_done() {
		return $this->is_done;
	}
}
