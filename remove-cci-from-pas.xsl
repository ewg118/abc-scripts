<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
    Date: February 2022
    Function: Identity transform to remove CCI coins from the PAS dataset, and remove crm:E53_Places as well, since this are integrated via the Nomisma import -->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:crm="http://www.cidoc-crm.org/cidoc-crm/" xmlns:nmo="http://nomisma.org/ontology#" xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" exclude-result-prefixes="xs" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" indent="yes"/>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="nmo:NumismaticObject[dcterms:identifier[contains(., 'CCI')]]"/>

    <xsl:template match="crm:E53_Place[@rdf:about] | geo:SpatialThing | geo:location"/>

</xsl:stylesheet>
