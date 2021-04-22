<?php

namespace BlueSpice\FlaggedRevsConnector\Entity\Collection;

class NamespacedFlaggedPages extends FlaggedPages {
	public const TYPE = 'flaggedpages-ns';

	public const ATTR_NAMESPACE_NAME = 'namespacename';
}
