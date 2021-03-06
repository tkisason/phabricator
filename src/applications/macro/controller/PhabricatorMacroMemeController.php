<?php

final class PhabricatorMacroMemeController
  extends PhabricatorMacroController {

  public function processRequest() {
    $request = $this->getRequest();
    $macro_name = $request->getStr('macro');
    $upper_text = $request->getStr('uppertext');
    $lower_text = $request->getStr('lowertext');
    $user = $request->getUser();
    $macro = id(new PhabricatorFileImageMacro())
      ->loadOneWhere('name=%s', $macro_name);
    if (!$macro) {
      return new Aphront404Response();
    }
    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $macro->getFilePHID());

    $upper_text = strtoupper($upper_text);
    $lower_text = strtoupper($lower_text);
    $mixed_text = $upper_text.":".$lower_text;
    $hash = "meme".hash("sha256", $mixed_text);
    $xform = id(new PhabricatorTransformedFile())
      ->loadOneWhere('originalphid=%s and transform=%s',
        $file->getPHID(), $hash);

    if ($xform) {
      $memefile = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s', $xform->getTransformedPHID());
      return id(new AphrontRedirectResponse())->setURI($memefile->getBestURI());
    }
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $transformers = (new PhabricatorImageTransformer());
    $newfile = $transformers
      ->executeMemeTransform($file, $upper_text, $lower_text);
    $xfile = new PhabricatorTransformedFile();
    $xfile->setOriginalPHID($file->getPHID());
    $xfile->setTransformedPHID($newfile->getPHID());
    $xfile->setTransform($hash);
    $xfile->save();
    return id(new AphrontRedirectResponse())->setURI($newfile->getBestURI());
  }
}
