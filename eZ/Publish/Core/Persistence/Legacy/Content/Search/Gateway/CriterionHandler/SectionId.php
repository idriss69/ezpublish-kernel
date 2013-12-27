<?php
/**
 * File containing the EzcDatabase section criterion handler class
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Legacy\Content\Search\Gateway\CriterionHandler;

use eZ\Publish\Core\Persistence\Legacy\Content\Search\Gateway\CriterionHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Gateway\CriteriaConverter;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use ezcQuerySelect;

/**
 * Section criterion handler
 */
class SectionId extends CriterionHandler
{
    /**
     * Check if this criterion handler accepts to handle the given criterion.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Query\Criterion $criterion
     *
     * @return boolean
     */
    public function accept( Criterion $criterion )
    {
        return $criterion instanceof Criterion\SectionId;
    }

    /**
     * Generate query expression for a Criterion this handler accepts
     *
     * accept() must be called before calling this method.
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Search\Gateway\CriteriaConverter $converter
     * @param \ezcQuerySelect $query
     * @param \eZ\Publish\API\Repository\Values\Content\Query\Criterion $criterion
     *
     * @return \ezcQueryExpression
     */
    public function handle( CriteriaConverter $converter, ezcQuerySelect $query, Criterion $criterion )
    {
        return $query->expr->in(
            $this->dbHandler->quoteColumn( 'section_id', 'ezcontentobject' ),
            $criterion->value
        );
    }
}

