<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="text" version="1.0" encoding="UTF-8" indent="yes"/>

<xsl:template match="mISDNconf">

<xsl:for-each select="card">
<xsl:value-of select="@type" /><xsl:text>,</xsl:text><xsl:value-of select="count(port)" />

<xsl:for-each select="port">
    <xsl:sort select="." />

    <xsl:text>,</xsl:text><xsl:value-of select="@mode" />
</xsl:for-each>

<xsl:text>
</xsl:text>
</xsl:for-each>

</xsl:template>
</xsl:stylesheet>
