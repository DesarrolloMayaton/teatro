-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 05-02-2026 a las 14:05:48
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `trt_historico_evento`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `boletos`
--

DROP TABLE IF EXISTS `boletos`;
CREATE TABLE IF NOT EXISTS `boletos` (
  `id_boleto` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `id_funcion` int DEFAULT NULL,
  `id_asiento` int NOT NULL,
  `id_categoria` int DEFAULT NULL,
  `id_promocion` int DEFAULT NULL COMMENT 'La promoción que se aplicó (si hubo)',
  `codigo_unico` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `precio_base` decimal(10,2) NOT NULL,
  `descuento_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_final` decimal(10,2) NOT NULL,
  `tipo_boleto` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'adulto',
  `id_usuario` int DEFAULT NULL,
  `fecha_compra` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estatus` int NOT NULL DEFAULT '1' COMMENT '1=Activo, 0=Usado/Escaneado',
  PRIMARY KEY (`id_boleto`),
  UNIQUE KEY `idx_codigo_unico` (`codigo_unico`),
  UNIQUE KEY `idx_evento_funcion_asiento` (`id_evento`,`id_funcion`,`id_asiento`),
  KEY `id_asiento` (`id_asiento`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_promocion` (`id_promocion`),
  KEY `idx_boletos_funcion` (`id_funcion`)
) ENGINE=InnoDB AUTO_INCREMENT=983 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `boletos`
--

INSERT INTO `boletos` (`id_boleto`, `id_evento`, `id_funcion`, `id_asiento`, `id_categoria`, `id_promocion`, `codigo_unico`, `precio_base`, `descuento_aplicado`, `precio_final`, `tipo_boleto`, `id_usuario`, `fecha_compra`, `estatus`) VALUES
(569, 18, 22, 1, 54, NULL, 'TKT-6980BAC9E1745-0', 329.00, 17.00, 317.00, 'inapam', 1, '2026-02-02 21:08:25', 1),
(570, 18, 23, 1, 53, NULL, 'TKT-6980BAC9E1A9C-1', 167.00, 50.00, 159.00, 'inapam', 1, '2026-01-26 15:51:02', 1),
(571, 18, 24, 1, 53, NULL, 'TKT-6980BAC9E1C4C-2', 155.00, 38.00, 160.00, 'adulto', 1, '2026-01-08 18:53:58', 1),
(572, 18, 24, 2, 54, NULL, 'TKT-6980BAC9E1E44-3', 206.00, 0.00, 203.00, 'nino', 1, '2026-01-03 14:01:32', 1),
(573, 18, 23, 2, 53, NULL, 'TKT-6980BAC9E20D6-4', 189.00, 47.00, 181.00, 'adulto', 1, '2026-01-12 21:51:14', 1),
(574, 18, 22, 2, 53, NULL, 'TKT-6980BAC9E265B-5', 183.00, 37.00, 186.00, 'adulto', 1, '2026-01-27 13:07:58', 1),
(575, 18, 22, 3, 53, NULL, 'TKT-6980BAC9E2924-6', 198.00, 33.00, 211.00, 'nino', 1, '2026-01-23 10:01:02', 1),
(576, 18, 23, 3, 54, NULL, 'TKT-6980BAC9E2B86-7', 208.00, 0.00, 212.00, 'inapam', 1, '2026-01-28 11:29:55', 1),
(577, 18, 24, 3, 54, NULL, 'TKT-6980BAC9E2D97-8', 204.00, 0.00, 196.00, 'adulto', 1, '2026-01-28 21:22:20', 1),
(578, 18, 24, 4, 53, NULL, 'TKT-6980BAC9E2F9B-9', 161.00, 18.00, 176.00, 'inapam', 1, '2026-01-09 10:13:13', 1),
(579, 18, 23, 4, 53, NULL, 'TKT-6980BAC9E31B4-10', 132.00, 39.00, 135.00, 'inapam', 1, '2026-01-31 15:22:08', 1),
(580, 18, 22, 4, 54, NULL, 'TKT-6980BAC9E35B1-11', 224.00, 0.00, 238.00, 'adulto', 1, '2026-01-04 15:50:35', 1),
(581, 18, 22, 5, 53, NULL, 'TKT-6980BAC9E37D4-12', 178.00, 0.00, 190.00, 'inapam', 1, '2026-01-12 19:05:50', 1),
(582, 18, 23, 5, 54, NULL, 'TKT-6980BAC9E39D4-13', 323.00, 0.00, 314.00, 'inapam', 1, '2026-01-21 14:16:26', 1),
(583, 18, 24, 5, 53, NULL, 'TKT-6980BAC9E3D85-14', 163.00, 26.00, 159.00, 'adulto', 1, '2026-01-11 12:40:06', 1),
(584, 18, 24, 6, 54, NULL, 'TKT-6980BAC9E3FB9-15', 264.00, 0.00, 270.00, 'adulto', 1, '2026-01-27 20:55:55', 1),
(585, 18, 23, 6, 54, NULL, 'TKT-6980BAC9E41BD-16', 243.00, 0.00, 255.00, 'inapam', 1, '2026-01-28 13:30:43', 1),
(586, 18, 22, 6, 54, NULL, 'TKT-6980BAC9E440A-17', 331.00, 0.00, 317.00, 'nino', 1, '2026-01-03 21:09:48', 1),
(587, 18, 22, 7, 53, NULL, 'TKT-6980BAC9E4618-18', 117.00, 33.00, 108.00, 'nino', 1, '2026-01-22 12:53:17', 1),
(588, 18, 23, 7, 54, NULL, 'TKT-6980BAC9E48BE-19', 292.00, 0.00, 285.00, 'nino', 1, '2026-01-27 14:24:10', 1),
(589, 18, 24, 7, 54, NULL, 'TKT-6980BAC9E4B1F-20', 291.00, 19.00, 290.00, 'nino', 1, '2026-01-21 22:58:19', 1),
(590, 18, 24, 8, 53, NULL, 'TKT-6980BAC9E4F05-21', 103.00, 0.00, 91.00, 'inapam', 1, '2026-01-29 15:38:44', 1),
(591, 18, 23, 8, 54, NULL, 'TKT-6980BAC9E50CC-22', 217.00, 24.00, 224.00, 'nino', 1, '2026-01-08 10:27:46', 1),
(592, 18, 22, 8, 53, NULL, 'TKT-6980BAC9E5279-23', 131.00, 0.00, 128.00, 'adulto', 1, '2026-01-23 09:48:59', 1),
(593, 18, 22, 9, 54, NULL, 'TKT-6980BAC9E5424-24', 254.00, 42.00, 265.00, 'nino', 1, '2026-01-15 09:50:12', 1),
(594, 18, 23, 9, 54, NULL, 'TKT-6980BAC9E5593-25', 258.00, 25.00, 249.00, 'adulto', 1, '2026-01-05 13:15:11', 1),
(595, 18, 24, 9, 53, NULL, 'TKT-6980BAC9E58CF-26', 168.00, 0.00, 182.00, 'nino', 1, '2026-01-12 22:08:49', 1),
(596, 18, 24, 10, 53, NULL, 'TKT-6980BAC9E5B46-27', 187.00, 10.00, 174.00, 'nino', 1, '2026-01-18 17:28:06', 1),
(597, 18, 23, 10, 54, NULL, 'TKT-6980BAC9E5E3E-28', 321.00, 37.00, 312.00, 'inapam', 1, '2026-01-31 16:42:16', 1),
(598, 18, 22, 10, 54, NULL, 'TKT-6980BAC9E60E0-29', 225.00, 38.00, 234.00, 'nino', 1, '2026-01-20 17:08:13', 1),
(599, 18, 22, 11, 53, NULL, 'TKT-6980BAC9E634D-30', 163.00, 40.00, 148.00, 'inapam', 1, '2026-01-20 18:12:41', 1),
(600, 18, 23, 11, 53, NULL, 'TKT-6980BAC9E661C-31', 157.00, 0.00, 171.00, 'nino', 1, '2026-01-10 18:10:28', 1),
(601, 18, 24, 11, 54, NULL, 'TKT-6980BAC9E67ED-32', 323.00, 17.00, 333.00, 'nino', 1, '2026-01-28 13:32:48', 1),
(602, 18, 24, 12, 54, NULL, 'TKT-6980BAC9E6935-33', 283.00, 13.00, 293.00, 'nino', 1, '2026-01-07 21:20:11', 1),
(603, 18, 23, 12, 54, NULL, 'TKT-6980BAC9E6A39-34', 349.00, 24.00, 364.00, 'nino', 1, '2026-01-13 13:36:00', 1),
(604, 18, 22, 12, 54, NULL, 'TKT-6980BAC9E6B45-35', 307.00, 21.00, 307.00, 'nino', 1, '2026-01-18 11:09:13', 1),
(605, 18, 22, 13, 53, NULL, 'TKT-6980BAC9E6C3E-36', 161.00, 17.00, 174.00, 'inapam', 1, '2026-01-23 16:18:32', 1),
(606, 18, 23, 13, 54, NULL, 'TKT-6980BAC9E6D46-37', 334.00, 0.00, 330.00, 'nino', 1, '2026-01-08 17:03:27', 1),
(607, 18, 24, 13, 53, NULL, 'TKT-6980BAC9E6E3F-38', 126.00, 44.00, 115.00, 'nino', 1, '2026-01-17 13:14:59', 1),
(608, 18, 24, 14, 53, NULL, 'TKT-6980BAC9E6F59-39', 171.00, 29.00, 171.00, 'inapam', 1, '2026-01-15 17:36:54', 1),
(609, 18, 23, 14, 53, NULL, 'TKT-6980BAC9E7057-40', 140.00, 20.00, 153.00, 'inapam', 1, '2026-01-27 10:21:47', 1),
(610, 18, 22, 14, 54, NULL, 'TKT-6980BAC9E722A-41', 234.00, 19.00, 242.00, 'nino', 1, '2026-01-04 17:29:45', 1),
(611, 18, 22, 15, 53, NULL, 'TKT-6980BAC9E7340-42', 190.00, 49.00, 180.00, 'inapam', 1, '2026-01-10 10:20:51', 1),
(612, 18, 23, 15, 54, NULL, 'TKT-6980BAC9E7448-43', 338.00, 0.00, 336.00, 'nino', 1, '2026-01-05 21:23:10', 1),
(613, 18, 24, 15, 54, NULL, 'TKT-6980BAC9E759E-44', 299.00, 0.00, 296.00, 'nino', 1, '2026-01-06 22:26:10', 1),
(614, 18, 24, 16, 54, NULL, 'TKT-6980BAC9E769D-45', 236.00, 0.00, 251.00, 'adulto', 1, '2026-01-20 11:21:34', 1),
(615, 18, 23, 16, 54, NULL, 'TKT-6980BAC9E77BA-46', 333.00, 22.00, 337.00, 'adulto', 1, '2026-01-19 15:03:14', 1),
(616, 18, 22, 16, 53, NULL, 'TKT-6980BAC9E78EB-47', 154.00, 11.00, 141.00, 'adulto', 1, '2026-01-08 17:05:55', 1),
(617, 18, 22, 17, 54, NULL, 'TKT-6980BAC9E7A00-48', 276.00, 0.00, 275.00, 'inapam', 1, '2026-01-15 13:00:21', 1),
(618, 18, 23, 17, 54, NULL, 'TKT-6980BAC9E8BE8-49', 215.00, 0.00, 208.00, 'inapam', 1, '2026-01-13 11:30:50', 1),
(619, 18, 24, 17, 54, NULL, 'TKT-6980BAC9E8F13-50', 303.00, 0.00, 316.00, 'inapam', 1, '2026-01-22 17:04:42', 1),
(620, 18, 24, 18, 53, NULL, 'TKT-6980BAC9E92EE-51', 133.00, 0.00, 143.00, 'inapam', 1, '2026-01-28 17:17:50', 1),
(621, 18, 23, 18, 54, NULL, 'TKT-6980BAC9E94A0-52', 331.00, 0.00, 323.00, 'adulto', 1, '2026-01-03 21:53:42', 1),
(622, 18, 22, 18, 53, NULL, 'TKT-6980BAC9E95FB-53', 174.00, 0.00, 180.00, 'inapam', 1, '2026-01-05 11:06:00', 1),
(623, 18, 22, 19, 54, NULL, 'TKT-6980BAC9E9786-54', 306.00, 19.00, 312.00, 'adulto', 1, '2026-01-22 09:31:14', 1),
(624, 18, 23, 19, 54, NULL, 'TKT-6980BAC9E9A97-55', 246.00, 0.00, 253.00, 'adulto', 1, '2026-01-20 14:46:34', 1),
(625, 18, 24, 19, 54, NULL, 'TKT-6980BAC9E9C22-56', 230.00, 13.00, 238.00, 'nino', 1, '2026-01-09 16:37:50', 1),
(626, 18, 24, 20, 53, NULL, 'TKT-6980BAC9E9DCA-57', 197.00, 0.00, 194.00, 'inapam', 1, '2026-01-03 16:01:16', 1),
(627, 18, 23, 20, 54, NULL, 'TKT-6980BAC9E9FDA-58', 282.00, 0.00, 270.00, 'nino', 1, '2026-01-06 21:50:46', 1),
(628, 18, 22, 20, 54, NULL, 'TKT-6980BAC9EA196-59', 320.00, 0.00, 314.00, 'nino', 1, '2026-01-29 09:45:00', 1),
(629, 18, 22, 21, 54, NULL, 'TKT-6980BAC9EA493-60', 219.00, 40.00, 228.00, 'inapam', 1, '2026-01-18 18:39:58', 1),
(630, 18, 23, 21, 53, NULL, 'TKT-6980BAC9EA825-61', 187.00, 14.00, 175.00, 'inapam', 1, '2026-01-25 14:03:35', 1),
(631, 18, 24, 21, 54, NULL, 'TKT-6980BAC9EA9E0-62', 258.00, 30.00, 258.00, 'adulto', 1, '2026-01-17 19:57:56', 1),
(632, 18, 24, 22, 54, NULL, 'TKT-6980BAC9EAB05-63', 311.00, 0.00, 298.00, 'inapam', 1, '2026-01-18 11:06:50', 1),
(633, 18, 23, 22, 53, NULL, 'TKT-6980BAC9EADD5-64', 161.00, 0.00, 149.00, 'nino', 1, '2026-01-12 11:48:18', 1),
(634, 18, 22, 22, 53, NULL, 'TKT-6980BAC9EAF9D-65', 162.00, 14.00, 153.00, 'inapam', 1, '2026-01-06 18:12:07', 1),
(635, 18, 22, 23, 53, NULL, 'TKT-6980BAC9EB0EC-66', 172.00, 0.00, 158.00, 'adulto', 1, '2026-01-22 14:03:23', 1),
(636, 18, 23, 23, 53, NULL, 'TKT-6980BAC9EB240-67', 166.00, 0.00, 158.00, 'nino', 1, '2026-01-12 11:48:52', 1),
(637, 18, 24, 23, 53, NULL, 'TKT-6980BAC9EB38D-68', 167.00, 48.00, 180.00, 'adulto', 1, '2026-01-27 22:36:32', 1),
(638, 18, 24, 24, 53, NULL, 'TKT-6980BAC9EB4A6-69', 197.00, 18.00, 190.00, 'nino', 1, '2026-01-22 15:12:38', 1),
(639, 18, 23, 24, 53, NULL, 'TKT-6980BAC9EB5B9-70', 183.00, 0.00, 185.00, 'nino', 1, '2026-01-19 21:24:58', 1),
(640, 18, 22, 24, 53, NULL, 'TKT-6980BAC9EB7D1-71', 138.00, 43.00, 145.00, 'adulto', 1, '2026-01-11 13:19:18', 1),
(641, 18, 22, 25, 54, NULL, 'TKT-6980BAC9EBAAB-72', 248.00, 0.00, 235.00, 'nino', 1, '2026-01-17 19:56:03', 1),
(642, 18, 23, 25, 54, NULL, 'TKT-6980BAC9EBC0B-73', 243.00, 0.00, 242.00, 'inapam', 1, '2026-01-19 09:15:53', 1),
(643, 18, 24, 25, 53, NULL, 'TKT-6980BAC9EBD52-74', 128.00, 28.00, 132.00, 'inapam', 1, '2026-01-17 18:42:01', 1),
(644, 18, 24, 26, 54, NULL, 'TKT-6980BAC9EBE38-75', 338.00, 41.00, 350.00, 'nino', 1, '2026-01-05 17:00:58', 1),
(645, 18, 23, 26, 53, NULL, 'TKT-6980BAC9EBF83-76', 182.00, 0.00, 184.00, 'inapam', 1, '2026-01-24 20:21:51', 1),
(646, 18, 22, 26, 53, NULL, 'TKT-6980BAC9EC1F5-77', 157.00, 29.00, 145.00, 'adulto', 1, '2026-01-16 22:21:35', 1),
(647, 18, 22, 27, 54, NULL, 'TKT-6980BAC9EC39E-78', 295.00, 0.00, 297.00, 'adulto', 1, '2026-01-22 14:26:55', 1),
(648, 18, 23, 27, 54, NULL, 'TKT-6980BAC9EC53B-79', 275.00, 17.00, 277.00, 'adulto', 1, '2026-01-29 15:14:42', 1),
(649, 18, 24, 27, 53, NULL, 'TKT-6980BAC9EC65D-80', 134.00, 15.00, 133.00, 'inapam', 1, '2026-01-14 15:55:39', 1),
(650, 18, 24, 28, 53, NULL, 'TKT-6980BAC9EC85F-81', 123.00, 0.00, 117.00, 'inapam', 1, '2026-01-23 18:20:55', 1),
(651, 18, 23, 28, 53, NULL, 'TKT-6980BAC9EC9A3-82', 185.00, 0.00, 175.00, 'adulto', 1, '2026-01-09 13:19:11', 1),
(652, 18, 22, 28, 54, NULL, 'TKT-6980BAC9ECABC-83', 216.00, 46.00, 209.00, 'inapam', 1, '2026-01-26 17:08:26', 1),
(653, 18, 22, 29, 53, NULL, 'TKT-6980BAC9ECBDD-84', 186.00, 0.00, 180.00, 'nino', 1, '2026-01-16 19:20:45', 1),
(654, 18, 23, 29, 54, NULL, 'TKT-6980BAC9ECCF5-85', 230.00, 31.00, 218.00, 'inapam', 1, '2026-01-11 10:09:56', 1),
(655, 18, 24, 29, 54, NULL, 'TKT-6980BAC9ECE07-86', 345.00, 28.00, 351.00, 'inapam', 1, '2026-01-07 15:14:34', 1),
(656, 18, 24, 30, 54, NULL, 'TKT-6980BAC9ECF23-87', 281.00, 39.00, 274.00, 'inapam', 1, '2026-01-30 15:00:45', 1),
(657, 18, 23, 30, 54, NULL, 'TKT-6980BAC9ED0E7-88', 240.00, 0.00, 249.00, 'nino', 1, '2026-01-31 21:03:42', 1),
(658, 18, 22, 30, 54, NULL, 'TKT-6980BAC9ED276-89', 237.00, 0.00, 239.00, 'inapam', 1, '2026-01-31 13:57:01', 1),
(659, 18, 22, 31, 54, NULL, 'TKT-6980BAC9ED3F2-90', 270.00, 0.00, 259.00, 'inapam', 1, '2026-01-28 16:39:57', 1),
(660, 18, 23, 31, 53, NULL, 'TKT-6980BAC9ED605-91', 179.00, 0.00, 184.00, 'adulto', 1, '2026-01-03 16:09:59', 1),
(661, 18, 24, 31, 54, NULL, 'TKT-6980BAC9ED7D1-92', 232.00, 43.00, 229.00, 'adulto', 1, '2026-01-31 16:38:22', 1),
(662, 18, 24, 32, 53, NULL, 'TKT-6980BAC9ED931-93', 153.00, 0.00, 157.00, 'adulto', 1, '2026-01-09 15:09:37', 1),
(663, 18, 23, 32, 53, NULL, 'TKT-6980BAC9EDA60-94', 141.00, 42.00, 143.00, 'adulto', 1, '2026-01-31 11:19:00', 1),
(664, 18, 22, 32, 54, NULL, 'TKT-6980BAC9EDC0C-95', 295.00, 16.00, 287.00, 'adulto', 1, '2026-01-09 09:17:24', 1),
(665, 18, 22, 33, 54, NULL, 'TKT-6980BAC9EDD6B-96', 217.00, 31.00, 231.00, 'nino', 1, '2026-01-22 19:00:35', 1),
(666, 18, 23, 33, 54, NULL, 'TKT-6980BAC9EDF2D-97', 260.00, 21.00, 264.00, 'inapam', 1, '2026-01-11 22:01:24', 1),
(667, 18, 24, 33, 53, NULL, 'TKT-6980BAC9EE0AB-98', 105.00, 0.00, 120.00, 'nino', 1, '2026-01-27 17:54:28', 1),
(668, 18, 24, 34, 54, NULL, 'TKT-6980BAC9EE1C7-99', 297.00, 25.00, 300.00, 'adulto', 1, '2026-01-12 10:33:47', 1),
(669, 18, 23, 34, 54, NULL, 'TKT-6980BAC9EE2C3-100', 277.00, 0.00, 284.00, 'inapam', 1, '2026-01-22 22:35:50', 1),
(670, 18, 22, 34, 53, NULL, 'TKT-6980BAC9EE501-101', 191.00, 44.00, 202.00, 'adulto', 1, '2026-01-16 15:04:25', 1),
(671, 18, 22, 35, 54, NULL, 'TKT-6980BAC9EE635-102', 275.00, 0.00, 265.00, 'nino', 1, '2026-01-09 12:04:00', 1),
(672, 18, 23, 35, 54, NULL, 'TKT-6980BAC9EE75C-103', 258.00, 0.00, 248.00, 'adulto', 1, '2026-01-14 20:04:45', 1),
(673, 18, 24, 35, 54, NULL, 'TKT-6980BAC9EE8CB-104', 249.00, 0.00, 262.00, 'nino', 1, '2026-01-13 11:29:19', 1),
(674, 18, 24, 36, 54, NULL, 'TKT-6980BAC9EE9C0-105', 224.00, 31.00, 216.00, 'inapam', 1, '2026-01-25 16:37:48', 1),
(675, 18, 23, 36, 53, NULL, 'TKT-6980BAC9EEAAC-106', 149.00, 10.00, 163.00, 'inapam', 1, '2026-01-03 22:46:28', 1),
(676, 18, 22, 36, 53, NULL, 'TKT-6980BAC9EEBF5-107', 118.00, 49.00, 105.00, 'adulto', 1, '2026-02-01 22:41:33', 1),
(677, 18, 22, 37, 53, NULL, 'TKT-6980BAC9EED1E-108', 169.00, 34.00, 174.00, 'inapam', 1, '2026-01-17 20:30:50', 1),
(678, 18, 23, 37, 54, NULL, 'TKT-6980BAC9EEE54-109', 281.00, 12.00, 289.00, 'nino', 1, '2026-01-19 12:59:11', 1),
(679, 18, 24, 37, 54, NULL, 'TKT-6980BAC9EEF72-110', 284.00, 13.00, 298.00, 'inapam', 1, '2026-01-11 18:53:07', 1),
(680, 18, 24, 38, 54, NULL, 'TKT-6980BAC9EF17B-111', 301.00, 15.00, 310.00, 'nino', 1, '2026-01-10 18:19:47', 1),
(681, 18, 23, 38, 54, NULL, 'TKT-6980BAC9EF2AC-112', 265.00, 0.00, 262.00, 'adulto', 1, '2026-01-14 21:17:12', 1),
(682, 18, 22, 38, 53, NULL, 'TKT-6980BAC9EF3C1-113', 187.00, 0.00, 190.00, 'nino', 1, '2026-01-06 20:46:04', 1),
(683, 18, 22, 39, 54, NULL, 'TKT-6980BAC9EF4D4-114', 204.00, 46.00, 196.00, 'inapam', 1, '2026-01-29 19:44:58', 1),
(684, 18, 23, 39, 54, NULL, 'TKT-6980BAC9EF5DA-115', 241.00, 0.00, 226.00, 'adulto', 1, '2026-01-16 09:09:25', 1),
(685, 18, 24, 39, 53, NULL, 'TKT-6980BAC9EF785-116', 167.00, 0.00, 169.00, 'inapam', 1, '2026-01-22 16:47:07', 1),
(686, 18, 24, 40, 53, NULL, 'TKT-6980BAC9EF8AC-117', 189.00, 28.00, 182.00, 'inapam', 1, '2026-01-05 10:56:04', 1),
(687, 18, 23, 40, 53, NULL, 'TKT-6980BAC9EF9EC-118', 200.00, 46.00, 197.00, 'adulto', 1, '2026-01-12 18:18:44', 1),
(688, 18, 22, 40, 54, NULL, 'TKT-6980BAC9EFAF2-119', 261.00, 47.00, 260.00, 'adulto', 1, '2026-01-14 14:50:41', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE IF NOT EXISTS `categorias` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `id_evento` int DEFAULT NULL,
  `nombre_categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Ej: General, VIP, Balcón',
  `precio` decimal(10,2) NOT NULL COMMENT 'Precio base para esta categoría',
  `color` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '#E0E0E0' COMMENT 'Color hexadecimal para la UI (ej: #FF0000)',
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `idx_evento_categoria` (`id_evento`,`nombre_categoria`),
  KEY `idx_id_evento` (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evento`
--

DROP TABLE IF EXISTS `evento`;
CREATE TABLE IF NOT EXISTS `evento` (
  `id_evento` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `imagen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo` int NOT NULL COMMENT '1 = 420 asientos, 2 = 540 asientos',
  `inicio_venta` datetime NOT NULL,
  `cierre_venta` datetime NOT NULL,
  `finalizado` int NOT NULL DEFAULT '0' COMMENT '0=activo, 1=finalizado',
  `mapa_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'Almacena las asignaciones del mapa en formato JSON',
  PRIMARY KEY (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `evento`
--

INSERT INTO `evento` (`id_evento`, `titulo`, `descripcion`, `imagen`, `tipo`, `inicio_venta`, `cierre_venta`, `finalizado`, `mapa_json`) VALUES
(18, 'Cats - El Musical', 'Una producción espectacular que cautivará al público con su increíble puesta en escena.', 'uploads/default_event.jpg', 1, '2026-02-03 12:00:00', '2026-02-11 14:00:00', 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `funciones`
--

DROP TABLE IF EXISTS `funciones`;
CREATE TABLE IF NOT EXISTS `funciones` (
  `id_funcion` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `estado` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_funcion`),
  KEY `id_evento_idx` (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `funciones`
--

INSERT INTO `funciones` (`id_funcion`, `id_evento`, `fecha_hora`, `estado`) VALUES
(22, 18, '2026-03-07 20:00:00', 0),
(23, 18, '2026-04-01 16:00:00', 0),
(24, 18, '2026-04-03 17:00:00', 0),
(28, 18, '2026-02-11 12:00:00', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios_tipo_boleto`
--

DROP TABLE IF EXISTS `precios_tipo_boleto`;
CREATE TABLE IF NOT EXISTS `precios_tipo_boleto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_evento` int DEFAULT NULL COMMENT 'NULL = precio global para todos los eventos',
  `tipo_boleto` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `usa_diferenciados` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_evento_tipo` (`id_evento`,`tipo_boleto`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promociones`
--

DROP TABLE IF EXISTS `promociones`;
CREATE TABLE IF NOT EXISTS `promociones` (
  `id_promocion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nombre (ej: Early Bird, Cupón Verano)',
  `precio` int NOT NULL,
  `id_evento` int DEFAULT NULL COMMENT 'NULO = aplica a todos los eventos',
  `id_categoria` int DEFAULT NULL COMMENT 'NULO = aplica a todas las categorías',
  `fecha_desde` datetime DEFAULT NULL COMMENT 'NULO = sin fecha de inicio',
  `fecha_hasta` datetime DEFAULT NULL COMMENT 'NULO = sin fecha de fin',
  `min_cantidad` int NOT NULL DEFAULT '1' COMMENT 'Mínimo de boletos para aplicar',
  `tipo_regla` enum('automatica','codigo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'automatica',
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'El código a escribir (ej: VERANO20)',
  `modo_calculo` enum('porcentaje','fijo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Si descuenta % o un monto fijo $',
  `valor` decimal(10,2) NOT NULL COMMENT 'El valor (ej: 20.00 para 20%)',
  `condiciones` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_promocion`),
  UNIQUE KEY `idx_codigo` (`codigo`),
  KEY `id_evento` (`id_evento`),
  KEY `id_categoria` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
