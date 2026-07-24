<?php
namespace tests\fixtures\Commands;

/**
 * Fixture de test — instrumentación de metricas-oem. DTO puro sin
 * efectos de dominio, aislado de la cadena Ping/Complete para que el
 * conteo de command_dispatched sea determinista (sin cascada de
 * Reactions ni Commands adicionales).
 */
class TestSucceedingCommand {
}
