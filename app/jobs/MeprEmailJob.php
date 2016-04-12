<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprEmailJob extends MeprBaseJob
{
  public function perform() {
    if( !isset($this->class) || empty($this->class) ) {
      throw new Exception(__('"class" cannot be blank', 'memberpress'));
    }

    if( !isset($this->to) || empty($this->to) ) {
      throw new Exception(__('"to" cannot be blank', 'memberpress'));
    }

    if( !isset($this->values) ) {
      $this->values=null;
    }

    if( !isset($this->subject) ) {
      $this->subject=null;
    }

    if( !isset($this->body) ) {
      $this->body=null;
    }

    if( !isset($this->use_template) ) {
      $this->use_template=null;
    }

    if( !isset($this->content_type) ) {
      $this->content_type=null;
    }

    if( !isset($this->headers) ) {
      $this->headers=null;
    }

    $email = MeprEmailFactory::fetch( $this->class, 'MeprBaseEmail' );
    $email->to = $this->to;
    $email->send(
      $this->values,
      $this->subject,
      $this->body,
      $this->use_template,
      $this->content_type
    );
  }
}

